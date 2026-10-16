<?php

namespace ItHealer\LaravelTron\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Date;
use ItHealer\LaravelTron\Api\Api;
use ItHealer\LaravelTron\Api\HttpProvider;

class TronNode extends Model
{
    public $timestamps = false;

    protected ?Api $_api = null;

    protected $fillable = [
        'name',
        'title',
        'full_node',
        'solidity_node',
        'index_node',
        'block_number',
        'requests',
        'requests_at',
        'sync_at',
        'worked',
        'available',
    ];

    protected $casts = [
        'full_node' => 'json',
        'solidity_node' => 'json',
        'index_node' => 'json',
        'block_number' => 'integer',
        'requests' => 'integer',
        'requests_at' => 'date',
        'sync_at' => 'datetime',
        'worked' => 'boolean',
        'available' => 'boolean',
    ];

    public function wallets(): HasMany
    {
        /** @var class-string<TronWallet> $model */
        $model = config('tron.models.wallet');

        return $this->hasMany($model, 'node_id');
    }

    /**
     * Учесть запрос к узлу: счётчик requests за текущие сутки (новые сутки — с нуля). Одним
     * UPDATE, чтобы процессы с устаревшей копией модели не сбрасывали чужой счёт.
     */
    public function recordRequest(): void
    {
        if (! $this->exists) {
            return;
        }

        $now = Date::now();

        static::query()->whereKey($this->getKey())->toBase()->update([
            'requests' => $this->getConnection()->raw('case when requests_at >= '.$this->getConnection()->getPdo()->quote($now->toDateString()).' then requests + 1 else 1 end'),
            'requests_at' => $now->toDateString(),
        ]);
    }

    public function api(): Api
    {
        if( is_null( $this->_api ) ) {
            $fullNode = new HttpProvider(
                baseUri: $this->full_node['url'],
                headers: $this->full_node['headers'] ?? [],
                user: $this->full_node['username'] ?? null,
                password: $this->full_node['password'] ?? null,
                proxy: $this->full_node['proxy'] ?? null,
                onRequest: fn () => $this->recordRequest(),
            );

            $solidityNode = new HttpProvider(
                baseUri: $this->solidity_node['url'],
                headers: $this->solidity_node['headers'] ?? [],
                user: $this->solidity_node['username'] ?? null,
                password: $this->solidity_node['password'] ?? null,
                proxy: $this->solidity_node['proxy'] ?? null,
                onRequest: fn () => $this->recordRequest(),
            );

            // Optional separate provider for indexer (v1/accounts/...) endpoints, so a node
            // can use an RPC provider that lacks them (e.g. Alchemy) while transaction history
            // is still served by TronGrid. Falls back to fullNode when not configured.
            $indexNode = null;
            if (!empty($this->index_node['url'])) {
                $indexNode = new HttpProvider(
                    baseUri: $this->index_node['url'],
                    headers: $this->index_node['headers'] ?? [],
                    user: $this->index_node['username'] ?? null,
                    password: $this->index_node['password'] ?? null,
                    proxy: $this->index_node['proxy'] ?? null,
                onRequest: fn () => $this->recordRequest(),
                );
            }

            /** @var class-string<Api> $model */
            $model = config('tron.models.api');

            $this->_api = new $model(
                fullNode: $fullNode,
                solidityNode: $solidityNode,
                indexer: $indexNode,
            );
        }

        return $this->_api;
    }
}
