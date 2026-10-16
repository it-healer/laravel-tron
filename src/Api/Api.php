<?php

namespace ItHealer\LaravelTron\Api;

use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use kornrunner\Secp256k1;
use kornrunner\Signature\Signature;
use ItHealer\LaravelTron\Api\DTO\AccountResourcesDTO;
use ItHealer\LaravelTron\Api\DTO\TransactionInfoDTO;
use ItHealer\LaravelTron\Api\DTO\TransferDTO;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Api\DTO\AccountDTO;
use ItHealer\LaravelTron\Api\Methods\Transfer;
use ItHealer\LaravelTron\Api\Methods\Transfers;
use ItHealer\LaravelTron\Api\Methods\ContractCall;
use ItHealer\LaravelTron\Api\Methods\TRC20Transfer;
use ItHealer\LaravelTron\Api\Methods\TRC20Transfers;

class Api
{
    public readonly ApiManager $manager;

    /** @var ?array<string, mixed> */
    protected ?array $chainParameters = null;

    public function __construct(
        ?HttpProvider $fullNode = null,
        ?HttpProvider $solidityNode = null,
        ?HttpProvider $eventServer = null,
        ?HttpProvider $signServer = null,
        ?HttpProvider $explorer = null,
        ?HttpProvider $indexer = null,
    ) {
        $this->manager = new ApiManager(
            compact(
                'fullNode',
                'solidityNode',
                'eventServer',
                'signServer',
                'explorer',
                'indexer'
            )
        );

    }

    public function chainParameter(string $name, mixed $default = null): mixed
    {
        return $this->chainParameters()[$name] ?? $default;
    }

    /**
     * Chain parameters are voted on by super representatives and change over time
     * (`getEnergyFee` alone went 10 -> 140 -> 420 -> 100 sun), so they are read from the
     * node and cached. The bundled snapshot is only a fallback for when the node is
     * unreachable.
     *
     * @return array<string, mixed>
     */
    public function chainParameters(): array
    {
        if ($this->chainParameters !== null) {
            return $this->chainParameters;
        }

        $ttl = (int) config('tron.chain_parameters_ttl', 3600);

        $parameters = $ttl > 0
            ? Cache::remember(
                'tron:chain-parameters:'.sha1($this->manager->fullNode()->baseUri),
                $ttl,
                fn () => $this->fetchChainParameters()
            )
            : $this->fetchChainParameters();

        return $this->chainParameters = $parameters ?: $this->chainParametersFromFile();
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchChainParameters(): array
    {
        try {
            $response = $this->manager->request('wallet/getchainparameters');
        } catch (\Throwable $e) {
            Log::warning('Tron: unable to read chain parameters from the node, falling back to the bundled snapshot: '.$e->getMessage());

            return [];
        }

        return $this->normalizeChainParameters($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function chainParametersFromFile(): array
    {
        $contents = file_get_contents(__DIR__.'/Resources/chain_parameters.json');

        return $this->normalizeChainParameters(json_decode($contents, true) ?: []);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function normalizeChainParameters(array $response): array
    {
        $parameters = [];

        foreach ($response['chainParameter'] ?? [] as $item) {
            if (isset($item['key'], $item['value'])) {
                $parameters[$item['key']] = $item['value'];
            }
        }

        return $parameters;
    }

    public function isConnected(): array
    {
        return $this->manager->isConnected();
    }

    public function getAccount(string $address): AccountDTO
    {
        $address = AddressHelper::toBase58($address);

        $data = $this->manager->request('walletsolidity/getaccount', null, [
            'address' => AddressHelper::toHex($address),
        ]);

        return AccountDTO::fromArray($address, $data);
    }

    public function getAccountResources(string $address): AccountResourcesDTO
    {
        $address = AddressHelper::toBase58($address);

        $data = $this->manager->request('wallet/getaccountresource', null, [
            'address' => AddressHelper::toHex($address),
        ]);

        return AccountResourcesDTO::fromArray($address, $data);
    }

    /**
     * Latest solidity (irreversible) block number. The account balance is read from the
     * solidity node, so this is the block that `getAccount()` balance corresponds to. Used
     * to keep an outgoing transfer subtracted from the available balance until the confirmed
     * balance actually reflects the spend (the transfer's block has become irreversible).
     */
    public function getSolidityBlockNumber(): ?int
    {
        $data = $this->manager->request('walletsolidity/getnowblock');

        $blockNumber = $data['block_header']['raw_data']['number'] ?? null;

        return $blockNumber !== null ? (int) $blockNumber : null;
    }

    public function validateAddress(string $address, string &$error = null): bool
    {
        $data = $this->manager->request('wallet/validateaddress', null, [
            'address' => $address,
        ]);
        if (!($data['result'] ?? false)) {
            $error = $data['message'] ?? print_r($data, true);
            return false;
        }

        return true;
    }

    public function getTransfers(string $address): Transfers
    {
        $address = AddressHelper::toBase58($address);

        return new Transfers($this->manager, $address);
    }

    public function getTRC20Transfers(string $address): TRC20Transfers
    {
        $address = AddressHelper::toBase58($address);

        return new TRC20Transfers($this->manager, $address);
    }

    public function getTRC20Contract(string $contractAddress): TRC20Contract
    {
        $contractAddress = AddressHelper::toBase58($contractAddress);

        return new TRC20Contract($this->manager, $contractAddress);
    }

    public function getTransactionInfo(string $txid): TransactionInfoDTO
    {
        $data = $this->manager->request('wallet/gettransactioninfobyid', null, [
            'value' => $txid
        ]);
        if (count($data) === 0) {
            throw new \Exception('Transaction '.$txid.' not found');
        }

        return TransactionInfoDTO::fromArray($data);
    }

    public function getTransfer(string $txid): ?TransferDTO
    {
        $data = $this->manager->request('wallet/gettransactionbyid', [
            'value' => $txid,
        ]);
        if (count($data) === 0) {
            throw new \Exception('Transaction '.$txid.' not found');
        }

        return TransferDTO::fromArray($data, true);
    }

    public function getTransferBlockNumber(string $txid): mixed
    {
        $data = $this->manager->request('wallet/gettransactioninfobyid', [
            'value' => $txid,
        ]);

        return $data['blockNumber'] ?? null;
    }

    /**
     * Block number and execution outcome of a transaction in a single
     * gettransactioninfobyid call. `failed` is true only for a transaction that was
     * included in a block but reverted / ran out of energy (top-level result "FAILED"
     * or a receipt result other than "SUCCESS"); a plain successful TRX transfer carries
     * no result and is therefore not failed. `blockNumber` is null while not yet mined.
     *
     * @return array{blockNumber: ?int, failed: bool}
     */
    public function getTransferStatus(string $txid): array
    {
        $data = $this->manager->request('wallet/gettransactioninfobyid', [
            'value' => $txid,
        ]);

        $blockNumber = $data['blockNumber'] ?? null;
        $result = $data['result'] ?? ($data['receipt']['result'] ?? null);

        return [
            'blockNumber' => $blockNumber !== null ? (int) $blockNumber : null,
            'failed' => $blockNumber !== null && $result !== null && $result !== 'SUCCESS',
        ];
    }

    public function transfer(string $from, string $to, string|int|float|BigDecimal $amount): Transfer
    {
        $from = AddressHelper::toBase58($from);
        $to = AddressHelper::toBase58($to);
        $amount = BigDecimal::of($amount);

        return new Transfer(
            api: $this,
            from: $from,
            to: $to,
            amount: $amount
        );
    }

    public function transferTRC20(
        string $contractAddress,
        string $from,
        string $to,
        string|int|float|BigDecimal $amount,
        string|int|float|BigDecimal $feeLimit = 30
    ): TRC20Transfer {
        $contract = $this->getTRC20Contract($contractAddress);
        $from = AddressHelper::toBase58($from);
        $to = AddressHelper::toBase58($to);
        $amount = BigDecimal::of($amount);
        $feeLimit = BigDecimal::of($feeLimit);

        return new TRC20Transfer(
            api: $this,
            contract: $contract,
            from: $from,
            to: $to,
            amount: $amount,
            feeLimit: $feeLimit,
        );
    }

    /**
     * Arbitrary smart contract call from a function selector and ABI-encoded parameters.
     *
     * @param  string  $parameter  ABI-encoded parameters (hex, without the 4-byte selector)
     * @param  string|int|float|BigDecimal  $callValue  TRX sent with the call
     * @param  string|int|float|BigDecimal  $feeLimit  max TRX to burn for energy
     */
    public function contractCall(
        string $from,
        string $contract,
        string $functionSelector,
        string $parameter,
        string|int|float|BigDecimal $callValue = 0,
        string|int|float|BigDecimal $feeLimit = 100,
    ): ContractCall {
        return new ContractCall(
            api: $this,
            from: AddressHelper::toBase58($from),
            contract: AddressHelper::toBase58($contract),
            functionSelector: $functionSelector,
            parameter: $parameter,
            callValue: BigDecimal::of($callValue),
            feeLimit: BigDecimal::of($feeLimit),
        );
    }

    /**
     * Read-only contract call (e.g. allowance(address,address)); returns the raw result hex.
     */
    public function constantCall(string $contract, string $functionSelector, string $parameter = '', ?string $owner = null): string
    {
        $data = $this->manager->request('wallet/triggerconstantcontract', null, [
            'owner_address' => $owner ? AddressHelper::toHex($owner) : '410000000000000000000000000000000000000000',
            'contract_address' => AddressHelper::toHex($contract),
            'function_selector' => $functionSelector,
            'parameter' => preg_replace('/^0x/i', '', $parameter),
        ]);

        if (! ($data['result']['result'] ?? null) || ! isset($data['constant_result'][0])) {
            $message = isset($data['result']['message']) ? @hex2bin($data['result']['message']) : null;

            throw new \Exception($message ?: json_encode($data));
        }

        return $data['constant_result'][0];
    }

    public function signTransaction(array $transaction, string $privateKey): array
    {
        $secp = new Secp256k1();

        /** @var Signature $sign */
        $sign = $secp->sign($transaction['txID'], $privateKey, ['canonical' => false]);
        $transaction['signature'] = $sign->toHex().bin2hex(implode('', array_map('chr', [$sign->getRecoveryParam()])));

        return $transaction;
    }
}
