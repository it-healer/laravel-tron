<?php

namespace ItHealer\LaravelTron\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use ItHealer\LaravelTron\Casts\BigDecimalCast;
use ItHealer\LaravelTron\Casts\EncryptedCast;

class TronWallet extends Model
{
    protected static array $plainPasswords = [];

    protected $fillable = [
        'node_id',
        'name',
        'title',
        'password',
        'mnemonic',
        'seed',
        'derivation_path',
        'sync_at',
        'balance',
        'trc20'
    ];

    protected $hidden = [
        'password',
        'mnemonic',
        'seed',
        'trc20',
    ];

    protected $appends = [
        'trc20_balances',
        'available_balance',
        'available_trc20_balances',
        'has_password',
        'has_mnemonic',
        'has_seed',
    ];

    protected $casts = [
        'password' => 'encrypted',
        'mnemonic' => EncryptedCast::class,
        'seed' => EncryptedCast::class,
        'sync_at' => 'datetime',
        'balance' => BigDecimalCast::class,
        'trc20' => 'json',
    ];

    public function node(): BelongsTo
    {
        return $this->belongsTo(TronNode::class, 'node_id');
    }

    public function addresses(): HasMany
    {
        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        return $this->hasMany($addressModel, 'wallet_id');
    }

    public function transactions(): HasManyThrough
    {
        /** @var class-string<TronTransaction> $transactionModel */
        $transactionModel = config('tron.models.transaction');

        /** @var class-string<TronAddress> $addressModel */
        $addressModel = config('tron.models.address');

        return $this->hasManyThrough(
            $transactionModel,
            $addressModel,
            'wallet_id',
            'address',
            'id',
            'address'
        );
    }

    protected function trc20Balances(): Attribute
    {
        return new Attribute(
            get: fn () => TronTRC20::get()->map(fn (TronTRC20 $trc20) => [
                ...$trc20->only(['address', 'name', 'symbol', 'decimals']),
                'balance' => $this->trc20[$trc20->address] ?? null,
            ])
        );
    }

    /**
     * Aggregated wallet TRX balance minus broadcast-but-unconfirmed outgoing transfers
     * (amount + fees) across all wallet addresses.
     */
    protected function availableBalance(): Attribute
    {
        return new Attribute(
            get: function (): string {
                $pending = \ItHealer\LaravelTron\Services\PendingBalance::forAddresses($this->addresses()->where('available', true)->pluck('address')->all());

                $native = \Brick\Math\BigDecimal::zero();
                $fee = \Brick\Math\BigDecimal::zero();
                foreach ($pending as $row) {
                    $native = $native->plus($row['native']);
                    $fee = $fee->plus($row['fee']);
                }

                $available = \Brick\Math\BigDecimal::of($this->balance ?? 0)->minus($native)->minus($fee);

                return (string) ($available->isNegative() ? \Brick\Math\BigDecimal::zero() : $available);
            }
        );
    }

    /**
     * Aggregated wallet TRC-20 balances reduced by pending outgoing token transfers.
     */
    protected function availableTrc20Balances(): Attribute
    {
        return new Attribute(
            get: function () {
                $pending = \ItHealer\LaravelTron\Services\PendingBalance::forAddresses($this->addresses()->where('available', true)->pluck('address')->all());

                $tokenPending = [];
                foreach ($pending as $row) {
                    foreach ($row['tokens'] as $contract => $amount) {
                        $tokenPending[$contract] = ($tokenPending[$contract] ?? \Brick\Math\BigDecimal::zero())->plus($amount);
                    }
                }

                return TronTRC20::get()->map(function (TronTRC20 $trc20) use ($tokenPending) {
                    $confirmed = $this->trc20[$trc20->address] ?? null;
                    $available = $confirmed !== null
                        ? \Brick\Math\BigDecimal::of($confirmed)->minus($tokenPending[$trc20->address] ?? \Brick\Math\BigDecimal::zero())
                        : null;

                    if ($available !== null && $available->isNegative()) {
                        $available = \Brick\Math\BigDecimal::zero();
                    }

                    return [
                        ...$trc20->only(['address', 'name', 'symbol', 'decimals']),
                        'balance' => $available !== null ? (string) $available : null,
                    ];
                });
            }
        );
    }

    public function deposits(): HasMany
    {
        /** @var class-string<TronDeposit> $model */
        $model = config('tron.models.deposit');

        return $this->hasMany($model, 'wallet_id');
    }

    public function unlockWallet(?string $password): void
    {
        self::$plainPasswords[$this->name] = $password;
    }

    public function getPlainPasswordAttribute(): ?string
    {
        return self::$plainPasswords[$this->name] ?? null;
    }

    public function getHasPasswordAttribute(): bool
    {
        return !!$this->password;
    }

    public function getHasMnemonicAttribute(): bool
    {
        return !!$this->mnemonic;
    }

    public function getHasSeedAttribute(): bool
    {
        return !!$this->seed;
    }
}
