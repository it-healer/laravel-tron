<?php

namespace ItHealer\LaravelTron;

use Illuminate\Database\Eloquent\Model;
use ItHealer\LaravelTron\Api\Api;
use ItHealer\LaravelTron\Concerns\Address;
use ItHealer\LaravelTron\Concerns\Mnemonic;
use ItHealer\LaravelTron\Concerns\Node;
use ItHealer\LaravelTron\Concerns\Transfer;
use ItHealer\LaravelTron\Concerns\TRC20;
use ItHealer\LaravelTron\Concerns\Wallet;
use ItHealer\LaravelTron\Enums\TronModel;
use ItHealer\LaravelTron\Models\TronNode;

class Tron
{
    use Node, Mnemonic, Wallet, Address, TRC20, Transfer;

    /**
     * Default BIP-44 derivation path template for TRON (coin type 195).
     * The {index} placeholder is replaced with the address index.
     */
    public const PATH_BIP44 = "m/44'/195'/0'/0/{index}";

    /**
     * @param TronModel $model
     * @return class-string<Model>
     */
    public function getModel(TronModel $model): string
    {
        return config('tron.models.'.$model->value);
    }

    /**
     * @return class-string<Api>
     */
    public function getApi(): string
    {
        return config('tron.models.api');
    }

    public function getNode(): TronNode
    {
        /** @var TronNode $node */
        $node = $this->getModel(TronModel::Node)::query()
            ->where('worked', '=', true)
            ->where('available', '=', true)
            ->orderBy('requests')
            ->firstOrFail();

        return $node;
    }
}
