<?php

namespace ItHealer\LaravelTron\Api\DTO;

use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Api\Helpers\AmountHelper;

class TransferDTO
{
    public function __construct(
        public readonly array   $data,
        public readonly string  $txid,
        public readonly ?Carbon  $time,
        public readonly bool    $success,
        public readonly ?int     $blockNumber,
        public readonly string  $from,
        public readonly string  $to,
        public readonly BigDecimal $value,
        public readonly bool $internal = false,
    )
    {
    }

    public function toArray(): array
    {
        return [
            'txid' => $this->txid,
            'time' => $this->time?->toDateTimeString(),
            'success' => $this->success,
            'blockNumber' => $this->blockNumber,
            'from' => $this->from,
            'to' => $this->to,
            'value' => $this->value->__toString(),
            'internal' => $this->internal,
        ];
    }

    /**
     * A plain TRX transfer (TransferContract) only.
     */
    public static function fromArray(array $data): ?static
    {
        if( ($data['raw_data']['contract'][0]['type'] ?? null) !== 'TransferContract' ) {
            return null;
        }
        $value = $data['raw_data']['contract'][0]['parameter']['value']['amount'];

        return new static(
            data: $data,
            txid: $data['txID'],
            time: isset($data['block_timestamp']) ? Date::createFromTimestampMs($data['block_timestamp']) : null,
            success: $data['ret'][0]['contractRet'] === 'SUCCESS',
            blockNumber: $data['blockNumber'] ?? null,
            from: AddressHelper::toBase58($data['raw_data']['contract'][0]['parameter']['value']['owner_address']),
            to: AddressHelper::toBase58($data['raw_data']['contract'][0]['parameter']['value']['to_address']),
            value: AmountHelper::sunToDecimal($value)
        );
    }

    /**
     * Any movement of TRX in an item of `v1/accounts/{address}/transactions`:
     *
     * - a plain transfer (TransferContract);
     * - a contract call with `call_value` (TRX → token swap, bridge deposit): from the caller to the contract;
     * - an internal transaction of a contract (token → TRX swap): `data.call_value._` sun from the contract
     *   to the address. Its item has no block number; `rejected` marks a failed one. Other keys of
     *   `call_value` are TRC-10 tokens and are not TRX.
     *
     * Anything else (a token transfer, a call without TRX) returns null.
     */
    public static function fromTransaction(array $data): ?static
    {
        if (isset($data['internal_tx_id'])) {
            $amount = (int) ($data['data']['call_value']['_'] ?? 0);

            if ($amount <= 0 || !isset($data['tx_id'], $data['from_address'], $data['to_address'])) {
                return null;
            }

            return new static(
                data: $data,
                txid: $data['tx_id'],
                time: isset($data['block_timestamp']) ? Date::createFromTimestampMs($data['block_timestamp']) : null,
                success: !($data['data']['rejected'] ?? false),
                blockNumber: null,
                from: AddressHelper::toBase58($data['from_address']),
                to: AddressHelper::toBase58($data['to_address']),
                value: AmountHelper::sunToDecimal($amount),
                internal: true,
            );
        }

        $contract = $data['raw_data']['contract'][0] ?? null;

        if (($contract['type'] ?? null) === 'TriggerSmartContract') {
            $value = $contract['parameter']['value'] ?? [];
            $amount = (int) ($value['call_value'] ?? 0);

            if ($amount <= 0 || !isset($data['txID'], $value['owner_address'], $value['contract_address'])) {
                return null;
            }

            return new static(
                data: $data,
                txid: $data['txID'],
                time: isset($data['block_timestamp']) ? Date::createFromTimestampMs($data['block_timestamp']) : null,
                success: ($data['ret'][0]['contractRet'] ?? null) === 'SUCCESS',
                blockNumber: $data['blockNumber'] ?? null,
                from: AddressHelper::toBase58($value['owner_address']),
                to: AddressHelper::toBase58($value['contract_address']),
                value: AmountHelper::sunToDecimal($amount),
            );
        }

        return static::fromArray($data);
    }
}
