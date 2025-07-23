<?php

return [
    /*
     * Touch Synchronization System (TSS) config
     * If there are many addresses in the system, we synchronize only those that have been touched recently.
     * You must update touch_at in TronAddress, if you want sync here.
     */
    'touch' => [
        /*
         * Is system enabled?
         */
        'enabled' => false,

        /*
         * The time during which the address is synchronized after touching it (in seconds).
         */
        'waiting_seconds' => 3600,
    ],

    /*
     * Sets the handler to be used when Tron Wallet
     * receives a new deposit.
     */
    'webhook_handler' => \ItHealer\LaravelTron\Handlers\EmptyWebhookHandler::class,

    /*
     * Set model class for both TronWallet, TronAddress, TronTrc20,
     * to allow more customization.
     *
     * TronApi model must be or extend `ItHealer\LaravelTron\Api\Api::class`
     * TronNode model must be or extend `ItHealer\LaravelTron\Models\TronNode::class`
     * TronWallet model must be or extend `ItHealer\LaravelTron\Models\TronWallet::class`
     * TronAddress model must be or extend `ItHealer\LaravelTron\Models\TronAddress::class`
     * TronTrc20 model must be or extend `ItHealer\LaravelTron\Models\TronTrc20::class`
     * TronTransaction model must be or extend `ItHealer\LaravelTron\Models\TronTransaction::class`
     * TronDeposit model must be or extend `ItHealer\LaravelTron\Models\TronDeposit::class`
     */
    'models' => [
        'api' => \ItHealer\LaravelTron\Api\Api::class,
        'node' => \ItHealer\LaravelTron\Models\TronNode::class,
        'wallet' => \ItHealer\LaravelTron\Models\TronWallet::class,
        'address' => \ItHealer\LaravelTron\Models\TronAddress::class,
        'trc20' => \ItHealer\LaravelTron\Models\TronTRC20::class,
        'transaction' => \ItHealer\LaravelTron\Models\TronTransaction::class,
        'deposit' => \ItHealer\LaravelTron\Models\TronDeposit::class,
    ]
];
