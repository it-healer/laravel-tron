<?php

namespace ItHealer\LaravelTron\Api\DTO;

use Brick\Math\BigDecimal;

/**
 * Preview of an arbitrary smart contract call: energy and bandwidth it needs, the TRX
 * that will be burnt for the missing resources, and the unsigned transaction.
 */
class ContractCallPreviewDTO
{
    public function __construct(
        public readonly ?string $error,
        public readonly ?BigDecimal $balanceBefore,
        public readonly ?BigDecimal $balanceAfter,
        public readonly ?int $energyRequired,
        public readonly ?int $energyBefore,
        public readonly ?BigDecimal $energyFee,
        public readonly ?int $bandwidthRequired,
        public readonly ?int $bandwidthBefore,
        public readonly ?BigDecimal $bandwidthFee,
        public readonly ?array $transaction,
    ) {}

    public function hasError(): bool
    {
        return (bool) $this->error;
    }

    /**
     * TRX burnt for energy and bandwidth (plus the call value is not included).
     */
    public function fee(): BigDecimal
    {
        return ($this->energyFee ?? BigDecimal::zero())->plus($this->bandwidthFee ?? BigDecimal::zero());
    }

    public function toArray(): array
    {
        return [
            'error' => $this->error,
            'balance' => [
                'before' => $this->balanceBefore?->__toString(),
                'after' => $this->balanceAfter?->__toString(),
            ],
            'energy' => [
                'required' => $this->energyRequired,
                'before' => $this->energyBefore,
                'fee' => $this->energyFee?->__toString(),
            ],
            'bandwidth' => [
                'required' => $this->bandwidthRequired,
                'before' => $this->bandwidthBefore,
                'fee' => $this->bandwidthFee?->__toString(),
            ],
            'fee' => $this->fee()->__toString(),
        ];
    }
}
