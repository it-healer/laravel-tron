<?php

namespace ItHealer\LaravelTron\Services;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ItHealer\LaravelTron\Enums\TronTransactionType;
use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronTransaction;

/**
 * Computes the in-flight value of broadcast-but-not-yet-confirmed outgoing transfers,
 * so the UI can show a truthful "available" balance immediately after a withdrawal
 * instead of the stale confirmed balance. Tron has no account nonce, so stale pending
 * transfers are reconciled by TTL during sync.
 */
class PendingBalance
{
    /**
     * Sum of pending outgoing transfers per address, in one query.
     *
     * @param  string[]  $addresses
     * @return array<string, array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}>
     *                                Keyed by lowercased address.
     */
    public static function forAddresses(array $addresses): array
    {
        $addresses = array_values(array_unique(array_map(
            fn (string $address) => Str::lower($address),
            $addresses
        )));

        if ($addresses === []) {
            return [];
        }

        /** @var class-string<TronTransaction> $model */
        $model = config('tron.models.transaction');
        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        $txTable = (new $model)->getTable();
        $addrTable = (new $addressModel)->getTable();

        $placeholders = implode(',', array_fill(0, count($addresses), '?'));

        /*
         * An outgoing transfer stays "in flight" (subtracted from the available balance) until
         * it is reflected in the confirmed balance: either still unconfirmed (no block_number)
         * or confirmed at a block the solidity balance has not caught up to yet
         * (block_number > balance_block). The join supplies each address's balance_block — the
         * solidity block its stored balance corresponds to. This prevents the available balance
         * from briefly reverting to its pre-send value during the solidity finality lag.
         */
        $rows = $model::query()
            ->join(
                $addrTable,
                DB::raw("LOWER($addrTable.address)"),
                '=',
                DB::raw("LOWER($txTable.address)")
            )
            ->where("$txTable.type", TronTransactionType::OUTGOING)
            ->whereNull("$txTable.dropped_at")
            ->whereRaw("LOWER($txTable.address) IN ($placeholders)", $addresses)
            ->where(function ($query) use ($txTable, $addrTable) {
                $query
                    ->whereNull("$txTable.block_number")
                    ->orWhereColumn("$txTable.block_number", '>', "$addrTable.balance_block");
            })
            ->selectRaw("LOWER($txTable.address) as address_key, $txTable.trc20_contract_address as trc20_contract_address, SUM($txTable.amount) as amount_sum, SUM($txTable.fee) as fee_sum")
            ->groupBy('address_key', "$txTable.trc20_contract_address")
            ->toBase()
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $key = $row->address_key;
            $result[$key] ??= ['native' => BigDecimal::zero(), 'fee' => BigDecimal::zero(), 'tokens' => []];

            $result[$key]['fee'] = $result[$key]['fee']->plus((string) ($row->fee_sum ?? '0'));

            $contract = (string) ($row->trc20_contract_address ?? '');
            $amount = BigDecimal::of((string) ($row->amount_sum ?? '0'));

            if ($contract === '') {
                $result[$key]['native'] = $result[$key]['native']->plus($amount);
            } else {
                $result[$key]['tokens'][$contract] = ($result[$key]['tokens'][$contract] ?? BigDecimal::zero())->plus($amount);
            }
        }

        return $result;
    }

    /**
     * @return array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}
     */
    public static function forAddress(string $address): array
    {
        return static::forAddresses([$address])[Str::lower($address)]
            ?? ['native' => BigDecimal::zero(), 'fee' => BigDecimal::zero(), 'tokens' => []];
    }

    /**
     * @param  array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}  $pending
     */
    public static function availableNative(BigDecimal $balance, array $pending): BigDecimal
    {
        $available = $balance->minus($pending['native'])->minus($pending['fee']);

        return $available->isNegative() ? BigDecimal::zero() : $available;
    }

    /**
     * @param  array{native: BigDecimal, fee: BigDecimal, tokens: array<string, BigDecimal>}  $pending
     */
    public static function availableToken(string $contract, BigDecimal|string|int|float|null $tokenBalance, array $pending): BigDecimal
    {
        $available = BigDecimal::of($tokenBalance ?? 0)->minus($pending['tokens'][$contract] ?? BigDecimal::zero());

        return $available->isNegative() ? BigDecimal::zero() : $available;
    }
}
