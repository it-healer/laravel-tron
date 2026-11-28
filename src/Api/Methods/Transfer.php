<?php

namespace ItHealer\LaravelTron\Api\Methods;

use Brick\Math\BigDecimal;
use ItHealer\LaravelTron\Api\Api;
use ItHealer\LaravelTron\Api\DTO\TransferPreviewDTO;
use ItHealer\LaravelTron\Api\DTO\TransferSendDTO;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Api\Helpers\AmountHelper;

class Transfer
{
    protected ?TransferPreviewDTO $preview = null;

    public function __construct(
        protected readonly Api  $api,
        public readonly string  $from,
        public readonly string  $to,
        public readonly BigDecimal $amount,
    )
    {
    }

    /**
     * @param  bool  $toActivated  The recipient is activated by an earlier transfer of the same
     *                             batch (several recipients previewed one after another)
     */
    public function preview(
        BigDecimal|float|int|string|null $balance = null,
        ?int $bandwidth = null,
        bool $toActivated = false,
    ): TransferPreviewDTO
    {
        if( $this->preview !== null ) {
            return $this->preview;
        }

        $error = null;
        $from = null;
        $fromResources = null;
        $to = null;
        $balanceBefore = $balance !== null ? BigDecimal::of($balance) : null;
        $balanceAfter = null;
        $activateFee = null;
        $transaction = null;
        $bandwidthRequired = null;
        $bandwidthBefore = $bandwidth;
        $bandwidthAfter = null;
        $bandwidthFee = null;

        try {
            $from = $this->api->getAccount($this->from);
            $fromResources = $this->api->getAccountResources($this->from);
            $to = $this->api->getAccount($this->to);

            if (!$from->activated) {
                throw new \Exception('From Address is not activated');
            }

            if( $balanceBefore === null ) {
                $balanceBefore = $from->balance;
            }
            $balanceAfter = $balanceBefore->minus($this->amount);

            $creates = !$to->activated && !$toActivated;

            // A new account costs the system contract fee (1 TRX) plus its bandwidth, which is
            // burned (getCreateAccountFee, 0.1 TRX) unless the sender has enough staked bandwidth:
            // free bandwidth is never used for it. Until the transaction size is known, assume burning
            if ($creates) {
                $activateFee = AmountHelper::sunToDecimal($this->api->chainParameter('getCreateNewAccountFeeInSystemContract', 1000000))
                    ->plus(AmountHelper::sunToDecimal($this->api->chainParameter('getCreateAccountFee', 100000)));
                $balanceAfter = $balanceAfter->minus($activateFee);
            }

            // Не прерываем расчёт при нехватке баланса: продолжаем считать комиссии,
            // чтобы вызывающий код мог показать, сколько TRX требуется для перевода.
            $insufficientBalance = $balanceAfter->isNegative();

            try {
                $transaction = $this->api->manager->request('wallet/createtransaction', null, [
                    'owner_address' => AddressHelper::toHex($this->from),
                    'to_address' => AddressHelper::toHex($this->to),
                    'amount' => AmountHelper::decimalToSun($this->amount),
                ]);

                $size = strlen($transaction['raw_data_hex']) + 1;
                $bandwidthRequired = $creates ? 0 : $size;

                if ($creates) {
                    $staked = (int) ($fromResources->data['NetLimit'] ?? 0) - (int) ($fromResources->data['NetUsed'] ?? 0);

                    if ($staked >= $size * (int) $this->api->chainParameter('getCreateNewAccountBandwidthRate', 1)) {
                        $burn = AmountHelper::sunToDecimal($this->api->chainParameter('getCreateAccountFee', 100000));
                        $activateFee = $activateFee->minus($burn);
                        $balanceAfter = $balanceAfter->plus($burn);
                    }
                }
                if( $bandwidthBefore === null ) {
                    $bandwidthBefore = $fromResources->bandwidthAvailable;
                }
                if( $bandwidthRequired > $bandwidthBefore ) {
                    $bandwidthFee = AmountHelper::sunToDecimal(($bandwidthRequired + 1) * $this->api->chainParameter('getTransactionFee', 1000));
                    $balanceAfter = $balanceAfter->minus($bandwidthFee);
                    $bandwidthAfter = 0;
                }
                else {
                    $bandwidthFee = BigDecimal::of(0);
                    $bandwidthAfter = $bandwidthBefore - $bandwidthRequired;
                }
            } catch (\Exception $e) {
                // Нода может отклонить создание транзакции при нехватке баланса —
                // в этом случае сохраняем понятную ошибку и уже рассчитанные данные
                if (! $insufficientBalance) {
                    throw $e;
                }
            }

            if ($balanceAfter->isNegative()) {
                throw new \Exception('Insufficient balance');
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        $this->preview = new TransferPreviewDTO(
            error: $error,
            from: $from,
            fromResources: $fromResources,
            to: $to,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            activateFee: $activateFee,
            transaction: $transaction,
            bandwidthRequired: $bandwidthRequired,
            bandwidthBefore: $bandwidthBefore,
            bandwidthAfter: $bandwidthAfter,
            bandwidthFee: $bandwidthFee
        );

        return $this->preview;
    }

    public function send(string $privateKey): TransferSendDTO
    {
        $preview = $this->preview();
        if( $preview->hasError() ) {
            throw new \Exception($preview->error);
        }

        $transaction = $this->api->signTransaction($preview->transaction, $privateKey);

        $data = $this->api->manager->request('wallet/broadcasttransaction', null, $transaction);
        if (!isset($data['txid'])) {
            throw new \Exception($response['Error'] ?? print_r($data, true));
        }

        $expiration = $transaction['raw_data']['expiration'] ?? null;

        return new TransferSendDTO(
            txid: $data['txid'],
            preview: $preview,
            expiration: $expiration !== null ? (int) $expiration : null,
        );
    }
}
