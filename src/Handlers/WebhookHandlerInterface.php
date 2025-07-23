<?php

namespace ItHealer\LaravelTron\Handlers;

use ItHealer\LaravelTron\Models\TronAddress;
use ItHealer\LaravelTron\Models\TronDeposit;
use ItHealer\LaravelTron\Models\TronTransaction;

interface WebhookHandlerInterface
{
    public function handle(TronDeposit $deposit): void;
}
