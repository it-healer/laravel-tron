<?php

namespace ItHealer\LaravelTron\Concerns;

use ItHealer\LaravelTron\Enums\TronModel;
use ItHealer\LaravelTron\Facades\Tron;
use ItHealer\LaravelTron\Models\TronNode;

trait Node
{
    public function createNode(string $name, ?string $title, array $fullNode, array $solidityNode): TronNode
    {
        if (!($fullNode['url'] ?? null)) {
            throw new \Exception('URL param not found in Full Node');
        }
        if (!($solidityNode['url'] ?? null)) {
            throw new \Exception('URL param not found in Solidity Node');
        }

        /** @var class-string<TronNode> $nodeModel */
        $nodeModel = Tron::getModel(TronModel::Node);
        $node = new $nodeModel([
            'name' => $name,
            'title' => $title,
            'full_node' => $fullNode,
            'solidity_node' => $solidityNode,
            'requests' => 1,
            'worked' => true,
            'available' => true,
        ]);

        $getBlock = $node->api()->manager->request('wallet/getblock');
        $blockNumber = $getBlock['block_header']['raw_data']['number'] ?? null;
        if (is_null($blockNumber)) {
            throw new \Exception('Current block is unknown!');
        }
        $node->block_number = $blockNumber;

        $node->save();

        return $node;
    }

    /**
     * Create a node that uses Alchemy for Tron RPC (balances, resources, broadcast,
     * triggerconstantcontract) while transaction history (v1/accounts/...) is served by
     * TronGrid, which Alchemy does not provide. Pass the TronGrid API key to keep history
     * (and therefore deposit detection) working.
     *
     * @param  string  $apiKey  Alchemy API key
     * @param  string|null  $tronGridApiKey  TronGrid API key for the indexer (history)
     * @param  string|null  $baseUrl  Override the Alchemy Tron base URL if needed
     */
    public function createAlchemyNode(
        string $apiKey,
        string $name,
        ?string $tronGridApiKey = null,
        ?string $title = null,
        ?string $baseUrl = null,
        ?string $proxy = null,
    ): TronNode {
        $url = rtrim($baseUrl ?: "https://tron-mainnet.g.alchemy.com/v2/{$apiKey}", '/').'/';

        /** @var class-string<TronNode> $nodeModel */
        $nodeModel = Tron::getModel(TronModel::Node);

        $rpc = ['url' => $url, 'proxy' => $proxy];

        $attributes = [
            'name' => $name,
            'title' => $title,
            'full_node' => $rpc,
            'solidity_node' => $rpc,
            'requests' => 1,
            'worked' => true,
            'available' => true,
        ];

        if ($tronGridApiKey) {
            $attributes['index_node'] = [
                'url' => 'https://api.trongrid.io',
                'headers' => ['TRON-PRO-API-KEY' => $tronGridApiKey],
                'proxy' => $proxy,
            ];
        }

        $node = new $nodeModel($attributes);

        $getBlock = $node->api()->manager->request('wallet/getblock');
        $blockNumber = $getBlock['block_header']['raw_data']['number'] ?? null;
        if (is_null($blockNumber)) {
            throw new \Exception('Current block is unknown!');
        }
        $node->block_number = $blockNumber;

        $node->save();

        return $node;
    }

    public function createTronGridNode(string $apiKey, string $name, ?string $title = null, ?string $proxy = null): TronNode
    {
        /** @var class-string<TronNode> $nodeModel */
        $nodeModel = Tron::getModel(TronModel::Node);

        $isUniqueApiKey = $nodeModel::query()->where('full_node', 'like', '%' . $apiKey . '%')->count() === 0;
        if (!$isUniqueApiKey) {
            throw new \Exception('API Key already exists.');
        }

        $node = new $nodeModel([
            'name' => $name,
            'title' => $title,
            'full_node' => [
                'url' => 'https://api.trongrid.io',
                'headers' => [
                    'TRON-PRO-API-KEY' => $apiKey,
                ],
                'proxy' => $proxy,
            ],
            'solidity_node' => [
                'url' => 'https://api.trongrid.io',
                'headers' => [
                    'TRON-PRO-API-KEY' => $apiKey,
                ],
                'proxy' => $proxy,
            ],
            'requests' => 1,
            'worked' => true,
            'available' => true,
        ]);

        $getBlock = $node->api()->manager->request('wallet/getblock');
        $blockNumber = $getBlock['block_header']['raw_data']['number'] ?? null;
        if (is_null($blockNumber)) {
            throw new \Exception('Current block is unknown!');
        }
        $node->block_number = $blockNumber;

        $node->save();

        return $node;
    }
}