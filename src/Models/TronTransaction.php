<?php

namespace ItHealer\LaravelTron\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use ItHealer\LaravelTron\Casts\BigDecimalCast;
use ItHealer\LaravelTron\Enums\TronTransactionType;

class TronTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'txid',
        'address',
        'type',
        'time_at',
        'from',
        'to',
        'amount',
        'fee',
        'trc20_contract_address',
        'block_number',
        'dropped_at',
        'debug_data',
    ];

    protected $appends = [
        'symbol'
    ];

    protected $casts = [
        'type' => TronTransactionType::class,
        'time_at' => 'datetime',
        'amount' => BigDecimalCast::class,
        'fee' => BigDecimalCast::class,
        'block_number' => 'integer',
        'dropped_at' => 'datetime',
        'debug_data' => 'json',
    ];

    /**
     * Outgoing transfers broadcast but not yet confirmed (no block_number) and not
     * reconciled as dropped — their amount and fee are still in flight. Tron has no
     * account nonce, so stale pending transfers are reconciled by TTL during sync.
     */
    public function scopePendingOutgoing(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->where('type', TronTransactionType::OUTGOING)
            ->whereNull('block_number')
            ->whereNull('dropped_at');
    }

    public function addresses(): HasMany
    {
        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        return $this->hasMany($addressModel, 'address', 'address');
    }

    public function wallets(): HasManyThrough
    {
        /** @var class-string<TronWallet> $walletModel */
        $walletModel = config('tron.models.wallet');

        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        return $this->hasManyThrough(
            $walletModel,
            $addressModel,
            'address',
            'id',
            'address',
            'wallet_id'
        );
    }

    public function trc20(): BelongsTo
    {
        return $this->belongsTo(TronTRC20::class, 'trc20_contract_address', 'address');
    }

    protected function symbol(): Attribute
    {
        return new Attribute(
            get: fn () => $this->trc20_contract_address ? ($this->trc20?->symbol ?: 'TOKEN') : 'TRX'
        );
    }
}
