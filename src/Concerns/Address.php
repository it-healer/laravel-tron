<?php

namespace ItHealer\LaravelTron\Concerns;

use BIP\BIP44;
use ItHealer\LaravelTron\Api\Helpers\AddressHelper;
use ItHealer\LaravelTron\Enums\TronModel;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronNode;
use ItHealer\LaravelTron\Models\TronWallet;
use ItHealer\LaravelTron\Support\Key;

trait Address
{
    public function createAddress(TronWallet $wallet, ?string $title = null, ?int $index = null, ?string $seed = null, ?string $derivationPath = null): TronAddress
    {
        $address = $this->newAddress($wallet, $title, $index, $seed, $derivationPath);
        $address->save();

        return $address;
    }

    public function newAddress(TronWallet $wallet, ?string $title = null, ?int $index = null, ?string $seed = null, ?string $derivationPath = null): TronAddress
    {
        if ($index === null) {
            $index = $wallet->addresses()->max('index');
            $index = $index === null ? 0 : ($index + 1);
        }

        if( !$seed ) {
            $seed = $wallet->seed;
        }

        if( !$seed ) {
            throw new \Exception('Argument Seed is required.');
        }

        $derivationPath ??= $wallet->derivation_path
            ?? config('tron.wallet.default_derivation_path', \ItHealer\LaravelTron\Tron::PATH_BIP44);

        $hdKey = BIP44::fromMasterSeed($seed)
            ->derive($this->resolveDerivationPath($derivationPath, $index));
        $privateKey = (string)$hdKey->privateKey;

        $addressString = AddressHelper::toBase58('41'.Key::privateKeyToAddress($privateKey));

        /** @var class-string<TronAddress> $addressModel */
        $addressModel = Tron::getModel(TronModel::Address);

        $address = new $addressModel([
            'address' => $addressString,
            'title' => $title,
            'index' => $index,
        ]);
        $address->wallet()->associate($wallet);
        $address->private_key = $privateKey;

        return $address;
    }

    /**
     * Resolves a derivation path template (e.g. "m/44'/195'/0'/0/{index}")
     * into a concrete path for the given address index.
     */
    public function resolveDerivationPath(string $pathTemplate, int $index): string
    {
        $path = str_replace('{index}', (string)$index, $pathTemplate);

        if (!$this->validateDerivationPath($path)) {
            throw new \InvalidArgumentException("Invalid derivation path: {$path}");
        }

        if (!str_contains($pathTemplate, '{index}') && $index > 0) {
            throw new \InvalidArgumentException(
                "Derivation path template \"{$pathTemplate}\" has no {index} placeholder, only index 0 is allowed."
            );
        }

        return $path;
    }

    public function validateDerivationPath(string $path): bool
    {
        return (bool)preg_match("/^m(\/\d+'?)+$/", str_replace('{index}', '0', $path));
    }

    public function importAddress(TronWallet $wallet, string $address)
    {
        return $wallet->addresses()->create([
            'address' => $address,
            'watch_only' => true,
        ]);
    }

    public function validateAddress(string $address, ?TronNode $node = null): bool
    {
        if( !$node ) {
            $node = Tron::getNode();
        }
        $node->increment('requests', 1);

        return $node->api()->validateAddress($address);
    }
}
