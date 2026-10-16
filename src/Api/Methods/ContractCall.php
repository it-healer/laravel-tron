<?php

namespace ItHealer\LaravelTron\Api\Methods;

use Brick\Math\BigDecimal;
use ItHealer\LaravelTron\Api\Api;
use ItHealer\LaravelTron\Api\DTO\ContractCallPreviewDTO;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Api\Helpers\AmountHelper;

/**
 * Arbitrary smart contract call built from a function selector and ABI-encoded parameters
 * (e.g. a swap transaction returned by a DEX/bridge API): energy estimate, then sign and
 * broadcast.
 */
class ContractCall
{
    protected ?ContractCallPreviewDTO $preview = null;

    /**
     * @param  string  $functionSelector  e.g. "approve(address,uint256)"
     * @param  string  $parameter  ABI-encoded parameters, hex without the selector
     * @param  BigDecimal  $callValue  TRX sent with the call
     * @param  BigDecimal  $feeLimit  max TRX to burn for energy
     */
    public function __construct(
        protected readonly Api $api,
        public readonly string $from,
        public readonly string $contract,
        public readonly string $functionSelector,
        public readonly string $parameter,
        public readonly BigDecimal $callValue,
        public readonly BigDecimal $feeLimit,
    ) {}

    public function preview(BigDecimal|float|int|string|null $balance = null, ?int $bandwidth = null, ?int $energy = null): ContractCallPreviewDTO
    {
        if ($this->preview !== null) {
            return $this->preview;
        }

        $error = null;
        $balanceBefore = $balance !== null ? BigDecimal::of($balance) : null;
        $balanceAfter = null;
        $energyRequired = null;
        $energyBefore = $energy;
        $energyFee = null;
        $bandwidthRequired = null;
        $bandwidthBefore = $bandwidth;
        $bandwidthFee = null;
        $transaction = null;

        try {
            $account = $this->api->getAccount($this->from);
            $resources = $this->api->getAccountResources($this->from);

            if (! $account->activated) {
                throw new \Exception('From Address is not activated');
            }

            $balanceBefore ??= $account->balance;
            $balanceAfter = $balanceBefore->minus($this->callValue);

            $constant = $this->request('wallet/triggerconstantcontract');
            $energyRequired = (int) ($constant['energy_used'] ?? 0);
            $energyBefore ??= $resources->energyAvailable;

            if ($energyRequired > $energyBefore) {
                $energyFee = AmountHelper::sunToDecimal($this->api->chainParameter('getEnergyFee', 100))->multipliedBy($energyRequired - $energyBefore);
                $balanceAfter = $balanceAfter->minus($energyFee);
            }

            $data = $this->request('wallet/triggersmartcontract');
            $transaction = $data['transaction'];

            $bandwidthRequired = strlen($transaction['raw_data_hex']) + 1;
            $bandwidthBefore ??= $resources->bandwidthAvailable;

            if ($bandwidthRequired > $bandwidthBefore) {
                $bandwidthFee = AmountHelper::sunToDecimal(($bandwidthRequired + 1) * $this->api->chainParameter('getTransactionFee', 1000));
                $balanceAfter = $balanceAfter->minus($bandwidthFee);
            }

            if ($this->feeLimit->minus($energyFee ?? 0)->isNegative()) {
                throw new \Exception('Fees over limit');
            }

            if ($balanceAfter->isNegative()) {
                throw new \Exception('Insufficient balance');
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        return $this->preview = new ContractCallPreviewDTO(
            error: $error,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            energyRequired: $energyRequired,
            energyBefore: $energyBefore,
            energyFee: $energyFee,
            bandwidthRequired: $bandwidthRequired,
            bandwidthBefore: $bandwidthBefore,
            bandwidthFee: $bandwidthFee,
            transaction: $transaction,
        );
    }

    /**
     * Sign and broadcast; returns the transaction id.
     */
    public function send(string $privateKey): string
    {
        $preview = $this->preview();

        if ($preview->hasError()) {
            throw new \Exception($preview->error);
        }

        $transaction = $this->api->signTransaction($preview->transaction, $privateKey);
        $data = $this->api->manager->request('wallet/broadcasttransaction', null, $transaction);

        if (! isset($data['txid'])) {
            $message = isset($data['message']) && ctype_xdigit((string) $data['message']) ? hex2bin($data['message']) : ($data['message'] ?? null);

            throw new \Exception($message ?: json_encode($data));
        }

        return $data['txid'];
    }

    protected function request(string $path): array
    {
        $data = $this->api->manager->request($path, null, [
            'owner_address' => AddressHelper::toHex($this->from),
            'contract_address' => AddressHelper::toHex($this->contract),
            'function_selector' => $this->functionSelector,
            'parameter' => preg_replace('/^0x/i', '', $this->parameter),
            'fee_limit' => AmountHelper::decimalToSun($this->feeLimit),
            'call_value' => AmountHelper::decimalToSun($this->callValue),
        ]);

        if (! ($data['result']['result'] ?? null)) {
            $message = isset($data['result']['message']) ? @hex2bin($data['result']['message']) : null;

            throw new \Exception($message ?: json_encode($data));
        }

        // Constant call ran the code and it reverted
        if ($path === 'wallet/triggerconstantcontract' && ($data['transaction']['ret'][0]['ret'] ?? null) === 'FAILED') {
            $message = isset($data['constant_result'][0]) ? $this->revertReason($data['constant_result'][0]) : null;
            $message ??= isset($data['result']['message']) ? (@hex2bin($data['result']['message']) ?: null) : null;

            throw new \Exception('Contract call reverted'.($message ? ': '.$message : ''));
        }

        return $data;
    }

    /**
     * Error(string) revert reason from the constant call result.
     */
    protected function revertReason(string $result): ?string
    {
        if (! str_starts_with($result, '08c379a0') || strlen($result) < 8 + 128) {
            return null;
        }

        $length = hexdec(substr($result, 8 + 64, 64));
        $text = @hex2bin(substr($result, 8 + 128, $length * 2));

        return $text !== false ? $text : null;
    }
}
