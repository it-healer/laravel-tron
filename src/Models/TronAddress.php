<?php

namespace ItHealer\LaravelTron\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ItHealer\LaravelTron\Casts\BigDecimalCast;
use ItHealer\LaravelTron\Casts\EncryptedCast;

class TronAddress extends Model
{
    protected $fillable = [
        'wallet_id',
        'address',
        'title',
        'watch_only',
        'private_key',
        'index',
        'sync_at',
        'activated',
        'balance',
        'balance_block',
        'trc20',
        'account',
        'account_resources',
        'touch_at',
        'available',
    ];

    protected $appends = [
        'trc20_balances',
        'available_balance',
        'available_trc20_balances',
    ];

    protected $hidden = [
        'private_key',
        'trc20',
    ];

    protected $casts = [
        'private_key' => EncryptedCast::class,
        'watch_only' => 'boolean',
        'sync_at' => 'datetime',
        'activated' => 'boolean',
        'balance' => BigDecimalCast::class,
        'balance_block' => 'integer',
        'trc20' => 'json',
        'account' => 'json',
        'account_resources' => 'json',
        'touch_at' => 'datetime',
        'available' => 'boolean',
    ];

    public function wallet(): BelongsTo
    {
        /** @var class-string<TronWallet> $model */
        $model = config('tron.models.wallet');

        return $this->belongsTo($model, 'wallet_id');
    }

    protected function trc20Balances(): Attribute
    {
        return new Attribute(
            get: fn () => TronTRC20::get()->map(fn (TronTRC20 $trc20) => [
                ...$trc20->only(['address', 'name', 'symbol', 'decimals']),
                'balance' => $this->trc20[$trc20->address] ?? null,
            ])->keyBy('address')
        );
    }

    /**
     * Native TRX balance minus broadcast-but-unconfirmed outgoing transfers (amount + fees),
     * so a withdrawal is reflected immediately, before the chain confirms it.
     */
    protected function availableBalance(): Attribute
    {
        return new Attribute(
            get: fn (): string => (string) \ItHealer\LaravelTron\Services\PendingBalance::availableNative(
                \Brick\Math\BigDecimal::of($this->balance ?? 0),
                \ItHealer\LaravelTron\Services\PendingBalance::forAddress((string) $this->address)
            )
        );
    }

    /**
     * TRC-20 balances (same shape as trc20_balances) reduced by pending outgoing token transfers.
     */
    protected function availableTrc20Balances(): Attribute
    {
        return new Attribute(
            get: function () {
                $pending = \ItHealer\LaravelTron\Services\PendingBalance::forAddress((string) $this->address);

                return TronTRC20::get()->map(fn (TronTRC20 $trc20) => [
                    ...$trc20->only(['address', 'name', 'symbol', 'decimals']),
                    'balance' => ($this->trc20[$trc20->address] ?? null) !== null
                        ? (string) \ItHealer\LaravelTron\Services\PendingBalance::availableToken($trc20->address, $this->trc20[$trc20->address], $pending)
                        : null,
                ])->keyBy('address');
            }
        );
    }

    public function transactions(): HasMany
    {
        /** @var class-string<TronTransaction> $model */
        $model = config('tron.models.transaction');

        return $this->hasMany($model, 'address', 'address');
    }

    public function deposits(): HasMany
    {
        /** @var class-string<TronDeposit> $model */
        $model = config('tron.models.deposit');

        return $this->hasMany($model, 'address_id');
    }

    public function getPlainPasswordAttribute(): ?string
    {
        return $this->wallet->plain_password;
    }

    public function getPasswordAttribute(): ?string
    {
        return $this->wallet->password;
    }
}
