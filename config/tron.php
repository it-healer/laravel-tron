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

    'sync' => [
        /*
         * Skip the transfer history (v1/accounts/{address}/transactions and .../trc20) on a
         * scheduled sync when nothing changed: the TRX balance and the TRC20 balances are the
         * same as at the last history scan (any transfer in or out, and any fee, changes one
         * of them). Forced syncs always read the history.
         *
         * settle_seconds   — after a change, keep reading the history for this long: the
         *                    balance comes from the solidity node and TronGrid indexes new
         *                    transfers with a delay.
         * confirmations    — while a recent deposit has fewer confirmations, keep reading the
         *                    history: confirmations grow only when it is read again.
         * max_skip_seconds — read the history at least this often anyway (0 = never force).
         */
        'change_detection' => [
            'enabled' => (bool)env('TRON_SYNC_CHANGE_DETECTION', true),
            'settle_seconds' => (int)env('TRON_SYNC_CHANGE_SETTLE_SECONDS', 600),
            'confirmations' => (int)env('TRON_SYNC_CHANGE_CONFIRMATIONS', 20),
            'max_skip_seconds' => (int)env('TRON_SYNC_CHANGE_MAX_SKIP_SECONDS', 21600),
        ],
    ],

    /*
     * Sets the handler to be used when Tron Wallet
     * receives a new deposit.
     */
    'webhook_handler' => \ItHealer\LaravelTron\Handlers\EmptyWebhookHandler::class,

    /*
     * Broadcast-but-unconfirmed outgoing transfers are subtracted from the confirmed
     * balance to show a truthful "available" balance. During sync each pending transfer
     * is resolved authoritatively by txid (mined => block_number). A Tron transaction
     * that passed its `expiration` without being included can never be mined, so it is
     * dropped `expiration_grace_seconds` after expiry (the grace absorbs the lag between
     * the head confirming a block and gettransactioninfobyid reflecting it). `ttl_minutes`
     * is a last-resort fallback for transfers stored before expiration was tracked
     * (null disables the TTL).
     */
    'pending' => [
        'ttl_minutes' => env('TRON_PENDING_TTL_MINUTES') !== null
            ? (int) env('TRON_PENDING_TTL_MINUTES')
            : 120,

        'expiration_grace_seconds' => (int) env('TRON_PENDING_EXPIRATION_GRACE_SECONDS', 60),

        /*
         * Transfers dropped within this many hours are looked up again on every sync and
         * restored if they were mined after all (null disables the re-check).
         */
        'recheck_dropped_hours' => env('TRON_PENDING_RECHECK_DROPPED_HOURS') !== null
            ? (int) env('TRON_PENDING_RECHECK_DROPPED_HOURS')
            : 24,
    ],

    /*
     * How long chain parameters read from the node (getEnergyFee, getTransactionFee, ...)
     * are cached, in seconds. 0 disables caching and reads them on every call.
     */
    'chain_parameters_ttl' => (int) env('TRON_CHAIN_PARAMETERS_TTL', 3600),

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
    ],

    /*
     * HTTP to nodes: time to resolve and connect, and how many times a request is repeated
     * when it failed before reaching the server (DNS did not answer, host unreachable) — safe to repeat.
     */
    'http' => [
        'connect_timeout' => (int)env('TRON_HTTP_CONNECT_TIMEOUT', 20),
        'retries' => (int)env('TRON_HTTP_RETRIES', 2),
        'retry_sleep_ms' => 500,
    ],
];
