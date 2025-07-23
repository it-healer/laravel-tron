<?php

namespace ItHealer\LaravelTron\Handlers;

use Illuminate\Support\Facades\Log;
use ItHealer\LaravelTron\Models\TronDeposit;

class EmptyWebhookHandler implements WebhookHandlerInterface
{
    public function handle(TronDeposit $deposit): void
    {
        Log::error('NEW DEPOSIT FOR ADDRESS '.$deposit->address->address.' = '.$deposit->txid);
    }
}
