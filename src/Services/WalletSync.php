<?php

namespace ItHealer\LaravelTron\Services;

use Illuminate\Support\Facades\App;
use ItHealer\LaravelTron\Models\TronWallet;

class WalletSync extends BaseSync
{
    public function __construct(
        protected readonly TronWallet $wallet,
    ) {
    }

    public function run(): void
    {
        parent::run();

        $this
            ->syncAddresses()
            ->calculateBalances();
    }

    protected function syncAddresses(): self
    {
        $addresses = $this->wallet
            ->addresses()
            ->syncable()
            ->get();

        foreach ($addresses as $address) {
            $this->log('- Started sync address '.$address->address.'...');

            $service = App::make(AddressSync::class, [
                'address' => $address
            ]);

            $service->setLogger($this->logger);

            $service->run();

            $this->log('- Finished sync address '.$address->address, 'success');
        }

        return $this;
    }

    protected function calculateBalances(): self
    {
        $this->wallet->recalculateBalances();

        return $this;
    }
}