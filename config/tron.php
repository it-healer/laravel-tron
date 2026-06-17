<?php

return [
    /*
     * Touch Synchronization System (TSS) config
     * If there are many addresses in the system, we synchronize only those that have been touched recently.
     * You must update touch_at in TronAddress, if you want sync here.
     */
    'touch' => [
        /*
         * Is the adaptive (touch-based) synchronization enabled?
         * When enabled, addresses are synced frequently while in use and rarely while idle.
         */
        'enabled' => false,

        /*
         * Active window: an address is considered "in use" for this many seconds after its
         * last touch (touch_at — set on user/merchant activity).
         */
        'waiting_seconds' => 3600,

        /*
         * Minimum seconds between syncs while the address is active (0 = every run).
         */
        'fast_interval' => 0,

        /*
         * Minimum seconds between syncs while the address is idle.
         * null = skip idle addresses entirely (legacy behavior).
         */
        'slow_interval' => null,
    ],

    /*
     * Sets the handler to be used when Tron Wallet
     * receives a new deposit.
     */
    'webhook_handler' => \ItHealer\LaravelTron\Handlers\EmptyWebhookHandler::class,

    /*
     * Broadcast-but-unconfirmed outgoing transfers are subtracted from the confirmed
     * balance to show a truthful "available" balance. They leave the pending set once
     * the explorer returns them confirmed. Tron has no account nonce, so `ttl_minutes`
     * is the safety net: a pending transfer older than this stops being subtracted
     * (null disables the TTL).
     */
    'pending' => [
        'ttl_minutes' => env('TRON_PENDING_TTL_MINUTES') !== null
            ? (int) env('TRON_PENDING_TTL_MINUTES')
            : 120,
    ],

    /*
     * Wallet settings.
     */
    'wallet' => [
        /*
         * Default BIP-44 derivation path template used when creating a wallet.
         * The {index} placeholder is replaced with the address index.
         * TRON coin type is 195. Preset: Tron::PATH_BIP44.
         */
        'default_derivation_path' => "m/44'/195'/0'/0/{index}",
    ],

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
