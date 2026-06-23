<?php

namespace ItHealer\LaravelTron\Services;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use ItHealer\LaravelTron\Api\Api;
use ItHealer\LaravelTron\Api\DTO\TransferDTO;
use ItHealer\LaravelTron\Api\DTO\TRC20TransferDTO;
use ItHealer\LaravelTron\Enums\TronTransactionType;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Handlers\WebhookHandlerInterface;
use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronDeposit;
use ItHealer\LaravelTron\Models\TronNode;
use ItHealer\LaravelTron\Models\TronTransaction;
use ItHealer\LaravelTron\Models\TronTRC20;
use ItHealer\LaravelTron\Models\TronWallet;

class AddressSync extends BaseSync
{
    protected readonly TronWallet $wallet;
    protected readonly TronNode $node;
    protected readonly Api $api;
    protected readonly ?WebhookHandlerInterface $webhookHandler;
    protected readonly array $trc20Addresses;
    /** @var TronDeposit[] $webhooks */
    protected array $webhooks = [];
    protected array $touchConfig = [];

    public function __construct(
        protected readonly TronAddress $address,
        protected readonly bool $force = false
    ) {
        $this->wallet = $this->address->wallet;
        $this->node = $this->wallet->node ?? Tron::getNode();
        $this->api = $this->node->api();
        $this->trc20Addresses = TronTRC20::pluck('address')->all();

        $model = config('tron.webhook_handler');
        $this->webhookHandler = $model ? App::make($model) : null;

        $this->touchConfig = config('tron.touch');
    }

    public function run(): void
    {
        parent::run();

        if( !$this->address->available ) {
            $this->log('No synchronization required, the address has not been available!', 'success');
            return;
        }

        if (
            ($this->touchConfig['enabled'] ?? false)
            && !$this->force
            && $this->shouldSkipBySchedule()
        ) {
            $this->log('No synchronization required by the adaptive touch schedule.', 'success');
            return;
        }

        $this
            ->accountWithResources()
            ->trc20Balances()
            ->transactions()
            ->reconcilePending()
            ->runWebhooks();
    }

    /**
     * Drop broadcast-but-still-pending outgoing transfers that never confirmed within the
     * TTL, so a stuck/dropped transaction stops being subtracted from the available balance
     * forever. Confirmed transfers leave the pending set automatically once the explorer
     * returns them with a block_number. Tron has no account nonce, so TTL is the safety net.
     */
    protected function reconcilePending(): self
    {
        $ttlMinutes = config('tron.pending.ttl_minutes');
        if ($ttlMinutes === null) {
            return $this;
        }

        $threshold = Date::now()->copy()->subMinutes((int) $ttlMinutes);

        TronTransaction::query()
            ->pendingOutgoing()
            ->where('address', $this->address->address)
            ->where('time_at', '<', $threshold)
            ->update(['dropped_at' => Date::now()]);

        return $this;
    }

    /**
     * Adaptive touch schedule: an address is "active" for `waiting_seconds` after its last
     * touch (user/merchant activity). While active it syncs no more often than `fast_interval`;
     * while idle, no more often than `slow_interval` (null = skip idle addresses entirely).
     */
    protected function shouldSkipBySchedule(): bool
    {
        $now = Date::now();

        $activeWindow = (int) ($this->touchConfig['waiting_seconds'] ?? 3600);
        $isActive = ! $this->address->touch_at
            || $this->address->touch_at >= $now->copy()->subSeconds($activeWindow);

        if ($isActive) {
            $interval = (int) ($this->touchConfig['fast_interval'] ?? 0);
        } else {
            $slowInterval = $this->touchConfig['slow_interval'] ?? null;

            if ($slowInterval === null) {
                return true;
            }

            $interval = (int) $slowInterval;
        }

        return $interval > 0
            && $this->address->sync_at
            && $this->address->sync_at >= $now->copy()->subSeconds($interval);
    }

    protected function accountWithResources(): self
    {
        $this->log('Method walletsolidity/getaccount started...');
        $getAccount = $this->api->getAccount($this->address->address);
        $this->log('Method walletsolidity/getaccount finished: '.print_r($getAccount->toArray(), true), 'success');

        $this->log('Method wallet/getaccountresource started...');
        $getAccountResources = $this->api->getAccountResources($this->address->address);
        $this->log(
            'Method wallet/getaccountresource finished: '.print_r($getAccountResources->toArray(), true),
            'success'
        );

        $attributes = [
            'activated' => $getAccount->activated,
            'balance' => $getAccount->balance,
            'account' => $getAccount->toArray(),
            'account_resources' => $getAccountResources->toArray(),
            'touch_at' => $this->address->touch_at ?: Date::now(),
        ];
        $requests = 2;

        /*
         * The balance comes from the solidity (irreversible) node, which lags the chain head
         * by ~19-20 blocks. An outgoing transfer gets its block_number from the head almost
         * immediately, so it would leave the pending set ~60s before the solidity balance
         * reflects the spend — briefly reverting the available balance to its pre-send value.
         * To avoid that, record the solidity block the balance corresponds to; PendingBalance
         * keeps subtracting an outgoing transfer until its block has become irreversible
         * (block_number <= balance_block). Only fetched while a transfer is still in flight,
         * so it costs nothing in the common idle case.
         */
        if ($this->hasInFlightOutgoing()) {
            $this->log('Method walletsolidity/getnowblock started...');
            $solidityBlock = $this->api->getSolidityBlockNumber();
            $this->log('Method walletsolidity/getnowblock finished: '.$solidityBlock, 'success');

            if ($solidityBlock !== null) {
                $attributes['balance_block'] = $solidityBlock;
            }
            $requests++;
        }

        $this->address->update($attributes);
        $this->node->increment('requests', $requests);

        return $this;
    }

    /**
     * Whether the address has an outgoing transfer that is not yet reflected in the confirmed
     * balance: still unconfirmed (no block_number) or confirmed at a block the solidity balance
     * has not caught up to yet. Only then do we need a fresh solidity block reference.
     */
    protected function hasInFlightOutgoing(): bool
    {
        $balanceBlock = $this->address->balance_block;

        return TronTransaction::query()
            ->where('type', TronTransactionType::OUTGOING)
            ->whereNull('dropped_at')
            ->where('address', $this->address->address)
            ->where(function ($query) use ($balanceBlock) {
                $query->whereNull('block_number');

                if ($balanceBlock !== null) {
                    $query->orWhere('block_number', '>', $balanceBlock);
                } else {
                    $query->orWhereNotNull('block_number');
                }
            })
            ->exists();
    }

    protected function trc20Balances(): self
    {
        $balances = [];

        foreach ($this->trc20Addresses as $trc20Address) {
            $this->log('Get TRC20 Balance from contract *'.$trc20Address.'* started...');
            $balance = Tron::getTRC20Balance($this->address, $trc20Address);
            $this->log(
                'Get TRC20 Balance from contract *'.$trc20Address.'* finished: '.$balance->__toString(),
                'success'
            );

            $balances[$trc20Address] = $balance->__toString();
        }

        $this->address->update([
            'trc20' => $balances,
        ]);

        $this->node->increment('requests', count($balances));

        return $this;
    }

    protected function transactions(): self
    {
        $minTimestamp = max(($this->address->sync_at?->getTimestamp() ?? 0) - 3600, 0) * 1000;

        $this->log('Method v1/accounts/'.$this->address->address.'/transactions started...');
        $transfers = $this->api
            ->getTransfers($this->address->address)
            ->limit(200)
            ->searchInterval(false)
            ->minTimestamp($minTimestamp);
        $this->log('Method v1/accounts/'.$this->address->address.'/transactions finished', 'success');

        $this->log('Method v1/accounts/'.$this->address->address.'/transactions/trc20 started...');
        $trc20Transfers = $this->api
            ->getTRC20Transfers($this->address->address)
            ->limit(200)
            ->minTimestamp($minTimestamp);
        $this->log('Method v1/accounts/'.$this->address->address.'/transactions/trc20 finished', 'success');

        $this->address->update([
            'sync_at' => Date::now(),
            'touch_at' => $this->address->touch_at ?: Date::now(),
        ]);
        $this->node->increment('requests', 2);

        foreach ($transfers as $item) {
            $this->handleTransfer($item);
        }

        foreach ($trc20Transfers as $item) {
            $this->handlerTRC20Transfer($item);
        }

        return $this;
    }

    protected function handleTransfer(TransferDTO $transfer): void
    {
        $type = $transfer->to === $this->address->address ?
            TronTransactionType::INCOMING : TronTransactionType::OUTGOING;

        TronTransaction::updateOrCreate([
            'txid' => $transfer->txid,
            'address' => $this->address->address,
        ], [
            'type' => $type,
            'time_at' => $transfer->time,
            'from' => $transfer->from,
            'to' => $transfer->to,
            'amount' => $transfer->value,
            'block_number' => $transfer->blockNumber,
            'debug_data' => $transfer->toArray(),
        ]);

        if ($type === TronTransactionType::INCOMING) {
            $deposit = $this->address
                ->deposits()
                ->updateOrCreate([
                    'txid' => $transfer->txid,
                ], [
                    'wallet_id' => $this->address->wallet_id,
                    'amount' => $transfer->value,
                    'block_height' => $transfer->blockNumber ?? 0,
                    'confirmations' => $transfer->blockNumber && $transfer->blockNumber < $this->node->block_number ? $this->node->block_number - $transfer->blockNumber : 0,
                    'time_at' => $transfer->time ?? Date::now(),
                ]);

            if ($deposit->wasRecentlyCreated) {
                $deposit->setRelation('wallet', $this->wallet);
                $deposit->setRelation('address', $this->address);

                $this->webhooks[] = $deposit;
            }
        }
    }

    protected function handlerTRC20Transfer(TRC20TransferDTO $transfer): void
    {
        if (!in_array($transfer->contractAddress, $this->trc20Addresses)) {
            return;
        }

        $type = $transfer->to === $this->address->address ?
            TronTransactionType::INCOMING : TronTransactionType::OUTGOING;

        $transaction = TronTransaction::updateOrCreate([
            'txid' => $transfer->txid,
            'address' => $this->address->address,
        ], [
            'type' => $type,
            'time_at' => $transfer->time,
            'from' => $transfer->from,
            'to' => $transfer->to,
            'amount' => $transfer->value,
            'trc20_contract_address' => $transfer->contractAddress,
            'debug_data' => $transfer->toArray(),
        ]);

        /*
         * The TRC-20 transfers endpoint does not return the block number, so an outgoing
         * transfer would otherwise keep block_number = null forever and stay "pending"
         * even after it is confirmed on-chain. Resolve it once via a dedicated lookup so
         * the transfer leaves the pending set as soon as the explorer reports it.
         * (Incoming transfers resolve their block number through the deposit branch below.)
         */
        if ($type === TronTransactionType::OUTGOING && ! $transaction->block_number) {
            try {
                $this->log('We request information about block number of outgoing TRC-20 transaction '.$transfer->txid.' ...');
                $blockNumber = $this->api->getTransferBlockNumber($transfer->txid);
                $this->node->increment('requests', 1);

                if ($blockNumber) {
                    $transaction->update(['block_number' => $blockNumber]);
                }
            } catch (\Exception $e) {
                $this->log('Error resolving TRC-20 block number for '.$transfer->txid.': '.$e->getMessage());
            }
        }

        if ($type === TronTransactionType::INCOMING) {
            $trc20 = TronTRC20::whereAddress($transfer->contractAddress)->first();
            if ($trc20) {
                $deposit = $this->address
                    ->deposits()
                    ->updateOrCreate([
                        'txid' => $transfer->txid,
                    ], [
                        'wallet_id' => $this->address->wallet_id,
                        'trc20_id' => $trc20->id,
                        'amount' => $transfer->value,
                        'time_at' => $transfer->time ?? Date::now(),
                    ]);

                if ($deposit->wasRecentlyCreated) {
                    $deposit->setRelation('wallet', $this->wallet);
                    $deposit->setRelation('address', $this->address);
                    $deposit->setRelation('trc20', $trc20);

                    $this->webhooks[] = $deposit;
                }

                if( !$deposit->block_height ) {
                    try {
                        $this->log('We request information about block number of TRC-20 transaction '.$transfer->txid.' ...');
                        $blockNumber = $this->api->getTransferBlockNumber($transfer->txid);
                        $this->log('Information received successfully: '.$blockNumber, 'success');

                        $deposit->update([
                            'block_height' => $blockNumber ?: null,
                            'confirmations' => $blockNumber && $blockNumber < $this->node->block_number ? $this->node->block_number - $blockNumber : 0,
                        ]);
                        $transaction->update([
                            'block_number' => $blockNumber ?: null,
                        ]);
                        $this->node->increment('requests', 1);
                    } catch (\Exception $e) {
                        $this->log('Error: '.$e->getMessage());
                    }
                }
                else {
                    $deposit->update([
                        'confirmations' => $deposit->block_height < $this->node->block_number ? $this->node->block_number - $deposit->block_height : 0,
                    ]);
                }
            }
        }
    }

    protected function runWebhooks(): self
    {
        if ($this->webhookHandler) {
            foreach ($this->webhooks as $item) {
                $this->log('Call Webhook Handler for Deposit #'.$item->id.': '.print_r($item->toArray(), true));

                $this->webhookHandler->handle($item);
            }
        }

        return $this;
    }
}