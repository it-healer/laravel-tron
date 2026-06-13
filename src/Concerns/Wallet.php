<?php

namespace ItHealer\LaravelTron\Concerns;

use ItHealer\LaravelTron\Enums\TronModel;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Models\TronNode;
use ItHealer\LaravelTron\Models\TronWallet;

trait Wallet
{
    public function importWallet(
        string $name,
        string|array $mnemonic,
        ?string $passphrase = null,
        ?string $password = null,
        ?bool $savePassword = true,
        ?TronNode $node = null,
        ?string $derivationPath = null,
    ): TronWallet {
        if (is_array($mnemonic)) {
            $mnemonic = implode(" ", $mnemonic);
        }

        $seed = Tron::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<TronWallet> $walletModel */
        $walletModel = Tron::getModel(TronModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'name' => $name,
        ]);
        $wallet->derivation_path = $this->walletDerivationPath($derivationPath);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = $mnemonic;
        $wallet->seed = $seed;

        return $wallet;
    }

    public function generateWallet(
        string $name,
        ?int $mnemonicSize = 18,
        ?string $passphrase = null,
        ?string $password = null,
        ?bool $savePassword = true,
        ?TronNode $node = null,
        ?string $derivationPath = null,
    ): TronWallet {
        $mnemonic = Tron::mnemonicGenerate($mnemonicSize ?? 18);
        $seed = Tron::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<TronWallet> $walletModel */
        $walletModel = Tron::getModel(TronModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'name' => $name,
        ]);
        $wallet->derivation_path = $this->walletDerivationPath($derivationPath);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = implode(" ", $mnemonic);
        $wallet->seed = $seed;

        return $wallet;
    }

    public function newWallet(
        string $name,
        ?string $password = null,
        ?bool $savePassword = true,
        ?TronNode $node = null,
        ?string $derivationPath = null,
    ): TronWallet {
        /** @var class-string<TronWallet> $walletModel */
        $walletModel = Tron::getModel(TronModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'name' => $name,
        ]);
        $wallet->derivation_path = $this->walletDerivationPath($derivationPath);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }

        return $wallet;
    }

    public function createWallet(
        string $name,
        ?string $password = null,
        ?bool $savePassword = true,
        string|array|int|null $mnemonic = null,
        ?string $passphrase = null,
        ?TronNode $node = null,
        ?string $derivationPath = null,
    ): TronWallet {
        if (is_string($mnemonic)) {
            $mnemonic = explode(' ', $mnemonic);
        } elseif (is_null($mnemonic) || is_int($mnemonic)) {
            $mnemonic = Tron::mnemonicGenerate($mnemonic ?? 18);
        }

        $seed = Tron::mnemonicSeed($mnemonic, $passphrase);

        /** @var class-string<TronWallet> $walletModel */
        $walletModel = Tron::getModel(TronModel::Wallet);

        $wallet = new $walletModel([
            'node_id' => $node?->id,
            'name' => $name,
        ]);
        $wallet->derivation_path = $this->walletDerivationPath($derivationPath);
        $wallet->unlockWallet($password);
        if ($savePassword) {
            $wallet->password = $password;
        }
        $wallet->mnemonic = implode(" ", $mnemonic);
        $wallet->seed = $seed;
        $wallet->save();

        Tron::createAddress($wallet, 'Primary Address', 0);

        return $wallet;
    }

    /**
     * Validates the wallet derivation path template, falling back to the configured default.
     */
    protected function walletDerivationPath(?string $derivationPath): string
    {
        $derivationPath ??= config(
            'tron.wallet.default_derivation_path',
            \ItHealer\LaravelTron\Tron::PATH_BIP44
        );

        if (!Tron::validateDerivationPath($derivationPath)) {
            throw new \InvalidArgumentException("Invalid derivation path template: {$derivationPath}");
        }

        return $derivationPath;
    }
}
