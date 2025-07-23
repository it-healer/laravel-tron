<?php

namespace ItHealer\LaravelTron\Concerns;

use Brick\Math\BigDecimal;
use ItHealer\LaravelTron\Enums\TronModel;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronNode;
use ItHealer\LaravelTron\Models\TronTRC20;

trait TRC20
{
    public function createTRC20(string $contractAddress, ?TronNode $node = null): TronTRC20
    {
        if( !$node ) {
            $node = Tron::getNode();
        }
        $node->increment('requests', 3);

        $contract = $node->api()->getTRC20Contract($contractAddress);

        /** @var class-string<TronTRC20> $model */
        $model = Tron::getModel(TronModel::TRC20);

        return $model::create([
            'address' => $contract->address,
            'name' => $contract->name(),
            'symbol' => $contract->symbol(),
            'decimals' => $contract->decimals(),
        ]);
    }

    public function getTRC20Balance(TronAddress|string $address, TronTRC20|string $trc20, ?TronNode $node = null): BigDecimal
    {
        if( !$node ) {
            $node = Tron::getNode();
        }
        $node->increment('requests', 1);

        $contractAddress = $trc20 instanceof TronTRC20 ? $trc20->address : $trc20;
        $contract = $node->api()->getTRC20Contract($contractAddress);
        $address = $address instanceof TronAddress ? $address->address : $address;

        return $contract->balanceOf($address);
    }
}
