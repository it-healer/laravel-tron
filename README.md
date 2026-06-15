![Logo](docs/logo.jpeg)

# Laravel Tron Package

<p align="left">
    <a href="https://packagist.org/packages/it-healer/laravel-tron" target="_blank">
        <img src="https://img.shields.io/packagist/v/it-healer/laravel-tron.svg?style=flat-square" alt="Latest Version">
    </a>
    <a href="https://packagist.org/packages/it-healer/laravel-tron" target="_blank">
        <img src="https://img.shields.io/packagist/dt/it-healer/laravel-tron.svg?style=flat-square" alt="Total Downloads">
    </a>
    <a href="https://packagist.org/packages/it-healer/laravel-tron" target="_blank">
        <img src="https://img.shields.io/packagist/l/it-healer/laravel-tron.svg?style=flat-square" alt="License">
    </a>
    <a href="https://github.com/it-healer/laravel-tron/actions" target="_blank">
        <img src="https://img.shields.io/badge/tests-passing-brightgreen.svg?style=flat-square" alt="Tests">
    </a>
</p>

**Laravel Tron** is a comprehensive Laravel package for working with the Tron blockchain and TRC-20 tokens. It allows you to generate HD wallets using mnemonic phrases (BIP39/BIP44), validate addresses, check balances and resources, preview and send TRX/TRC-20 tokens. Automate cryptocurrency receiving and withdrawals in your Laravel application with ease.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Working with Nodes](#working-with-nodes)
  - [Working with Wallets](#working-with-wallets)
  - [Working with Addresses](#working-with-addresses)
  - [Working with TRX](#working-with-trx)
  - [Working with TRC-20 Tokens](#working-with-trc-20-tokens)
- [Advanced Usage](#advanced-usage)
- [Testing](#testing)
- [Artisan Commands](#artisan-commands)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Changelog](#changelog)
- [Support](#support)
- [Credits](#credits)
- [License](#license)

## Features

✨ **Key Features:**

- 🔐 **Built-in BIP39/BIP44 Support** - No external dependencies for mnemonic generation
- 💼 **HD Wallet Generation** - Create hierarchical deterministic wallets
- 🎯 **Multiple Address Support** - Generate unlimited addresses from a single seed
- 💰 **TRX & TRC-20 Support** - Full support for native TRX and TRC-20 tokens
- 🔄 **Automatic Synchronization** - Background sync of transactions and balances
- 📊 **Resource Management** - Track bandwidth and energy usage
- 🎨 **Customizable Models** - Extend default models to fit your needs
- 🔔 **Webhook Handler** - Custom event handling for deposits
- 🧪 **Fully Tested** - Comprehensive test suite with 17 tests
- 🛡️ **Secure** - Encrypted storage for sensitive data

## Requirements

- **PHP:** 8.1 or newer
- **Laravel:** 10.0 or newer (tested with Laravel 10, 11, and 12)
- **PHP Extensions:**
  - `ext-gmp` - GNU Multiple Precision arithmetic
  - `ext-ctype` - Character type checking
- **External Services:**
  - TronGrid API key ([Get one here](https://www.trongrid.io/register))

## Installation

Install the package via Composer:

```bash
composer require it-healer/laravel-tron
```

Run the installer command:

```bash
php artisan tron:install
```

This will publish the configuration file and migrations.

Run the migrations:

```bash
php artisan migrate
```

### Laravel 11+ (Automatic Discovery)

The package will be automatically discovered. No additional steps needed!

### Laravel 10 (Manual Registration)

For Laravel 10, register the Service Provider and Facade in `config/app.php`:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // ...
    \ItHealer\LaravelTron\TronServiceProvider::class,
])->toArray(),

'aliases' => Facade::defaultAliases()->merge([
    // ...
    'Tron' => \ItHealer\LaravelTron\Facades\Tron::class,
])->toArray(),
```

### Scheduler Setup

**For Laravel 10:**

Edit `app/Console/Kernel.php` and add to the `schedule()` method:

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('tron:sync')
        ->everyMinute()
        ->runInBackground();
}
```

**For Laravel 11+:**

Add to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tron:sync')
    ->everyMinute()
    ->runInBackground();
```

## Configuration

After installation, you'll find the configuration file at `config/tron.php`:

```php
return [
    /*
     * Touch Synchronization System (TSS)
     * Optimize sync by only updating recently touched addresses
     */
    'touch' => [
        'enabled' => false,
        'waiting_seconds' => 3600, // 1 hour
    ],

    /*
     * Webhook handler for deposit events
     */
    'webhook_handler' => \ItHealer\LaravelTron\Handlers\EmptyWebhookHandler::class,

    /*
     * Custom model classes
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
```

### Custom Webhook Handler

Create a custom webhook handler to process deposit events:

```php
namespace App\Handlers;

use ItHealer\LaravelTron\Handlers\WebhookHandler;
use ItHealer\LaravelTron\Models\TronDeposit;

class CustomWebhookHandler extends WebhookHandler
{
    public function handle(TronDeposit $deposit): void
    {
        // Your custom logic here
        // Send notification, update user balance, etc.
    }
}
```

Then update `config/tron.php`:

```php
'webhook_handler' => \App\Handlers\CustomWebhookHandler::class,
```

## Quick Start

### 1. Register with TronGrid

First, create an account on [TronGrid](https://www.trongrid.io/register) and generate an [API key](https://www.trongrid.io/dashboard/keys).

### 2. Create a Tron Node

```php
use ItHealer\LaravelTron\Facades\Tron;

$apiKey = "your-trongrid-api-key";
$node = Tron::createTronGridNode($apiKey, 'MainNet Node');
```

### 3. Generate a Wallet

```php
// Generate a new mnemonic phrase (15 words by default)
$mnemonic = Tron::mnemonicGenerate(15);
echo 'Mnemonic: ' . implode(' ', $mnemonic);

// Create wallet from mnemonic
$wallet = Tron::createWallet('My Wallet', $mnemonic);
```

### 4. Create an Address

```php
// Primary address is created automatically when wallet is created
// Get the primary address
$address = $wallet->addresses()->first();

// Or create additional addresses
$secondaryAddress = Tron::createAddress($wallet, 'Secondary Address');

echo 'Address: ' . $address->address;
```

### 5. Send TRX

```php
$recipientAddress = 'TJCnKsPa7y5okkXvQAidZBzqx3QyQ6sxMW';
$amount = 10; // 10 TRX

$transfer = Tron::transfer($address, $recipientAddress, $amount);

echo 'Transaction ID: ' . $transfer->txid;
```

## Usage

### Working with Nodes

#### Create a Node

```php
use ItHealer\LaravelTron\Facades\Tron;

// Using TronGrid
$node = Tron::createTronGridNode($apiKey, 'Node Name');

// Custom node
$node = Tron::createNode('Node Name', 'https://api.trongrid.io');
```

#### Alchemy as RPC node

Alchemy can serve Tron **RPC** (balances, account resources, `triggerconstantcontract`,
broadcast), but **not** the TronGrid address-history endpoints (`v1/accounts/.../transactions`).
`createAlchemyNode()` points RPC at Alchemy and keeps history (and therefore deposit detection)
on TronGrid via a separate indexer provider — pass your TronGrid API key for that:

```php
$node = Tron::createAlchemyNode(
    apiKey: 'YOUR_ALCHEMY_KEY',
    name: 'alchemy',
    tronGridApiKey: 'YOUR_TRONGRID_KEY', // used only for v1 history; omit to fall back to Alchemy
);
```

Under the hood the node stores an optional `index_node` ({url, headers}); `ApiManager` routes
`v1/*` requests to it when set, everything else to the RPC node.

#### Get Node Information

```php
$node = Tron::getNode(); // Get default node
$apiUrl = $node->api_url;
$requestsCount = $node->requests;
```

### Working with Wallets

#### Generate Wallet

```php
// Generate with default 15 words
$mnemonic = Tron::mnemonicGenerate();

// Generate with custom word count (12, 15, 18, 21, or 24)
$mnemonic = Tron::mnemonicGenerate(24);

// Create wallet
$wallet = Tron::createWallet('Wallet Name', $mnemonic);
```

#### Import Existing Wallet

```php
$mnemonic = "your existing twenty four word mnemonic phrase here...";
$wallet = Tron::importWallet('Imported Wallet', $mnemonic);
```

#### Wallet with Passphrase

```php
$mnemonic = Tron::mnemonicGenerate();
$passphrase = "super-secret-passphrase";
$wallet = Tron::createWallet('Wallet Name', $mnemonic, $passphrase);
```

#### Validate Mnemonic

```php
$isValid = Tron::mnemonicValidate($mnemonic);
if ($isValid) {
    echo "Mnemonic is valid!";
}
```

### Working with Addresses

#### Create Address

```php
// Create with auto-incremented index
$address = Tron::createAddress($wallet, 'Address Label');

// Create with specific index
$address = Tron::newAddress($wallet, 'Custom Address', $index = 5);
```

#### Import Watch-Only Address

```php
$address = Tron::importAddress($wallet, 'TJCnKsPa7y5okkXvQAidZBzqx3QyQ6sxMW');
```

#### Validate Address

```php
$isValid = Tron::validateAddress('TJCnKsPa7y5okkXvQAidZBzqx3QyQ6sxMW');
```

#### Get Address Balance

```php
$balance = $address->balance; // Balance in TRX
$balanceSun = $address->balance_sun; // Balance in SUN (1 TRX = 1,000,000 SUN)
```

#### Get Address Resources

```php
$resources = $address->getResources();
echo "Bandwidth: " . $resources->bandwidth;
echo "Energy: " . $resources->energy;
```

### Working with TRX

#### Preview Transfer

```php
$preview = Tron::transferPreview($address, $recipientAddress, $amount);

echo "Fee: " . $preview->fee . " TRX";
echo "Total: " . $preview->total . " TRX";
```

#### Send TRX

```php
$transfer = Tron::transfer($address, $recipientAddress, $amount);

if ($transfer->success) {
    echo "Transaction sent! TXID: " . $transfer->txid;
} else {
    echo "Transfer failed: " . $transfer->error;
}
```

### Working with TRC-20 Tokens

#### Register TRC-20 Token

```php
use ItHealer\LaravelTron\Models\TronTRC20;

// USDT TRC-20 contract address
$contractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

$token = TronTRC20::create([
    'contract_address' => $contractAddress,
    'name' => 'Tether USD',
    'symbol' => 'USDT',
    'decimals' => 6,
]);
```

#### Get TRC-20 Balance

```php
$balance = $address->getTRC20Balance($token);
echo "USDT Balance: " . $balance;
```

#### Preview TRC-20 Transfer

```php
$preview = Tron::transferTRC20Preview($address, $recipientAddress, $amount, $token);

echo "Fee: " . $preview->fee . " TRX";
echo "Energy Required: " . $preview->energy_required;
```

#### Send TRC-20 Tokens

```php
$transfer = Tron::transferTRC20($address, $recipientAddress, $amount, $token);

if ($transfer->success) {
    echo "Transaction sent! TXID: " . $transfer->txid;
}
```

## Advanced Usage

### Custom Models

Extend the default models to add custom functionality:

```php
namespace App\Models;

use ItHealer\LaravelTron\Models\TronWallet as BaseTronWallet;

class TronWallet extends BaseTronWallet
{
    // Add custom methods or properties
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
```

Update `config/tron.php`:

```php
'models' => [
    'wallet' => \App\Models\TronWallet::class,
    // ...
],
```

### Touch Synchronization System (TSS) — adaptive sync

For applications with many addresses, enable TSS so addresses are polled **often while in use
and rarely while idle**, instead of every run. An address is "active" for `waiting_seconds`
after its last `touch_at`; while active it syncs no more often than `fast_interval`, while idle
no more often than `slow_interval`.

```php
// In config/tron.php
'touch' => [
    'enabled' => true,
    'waiting_seconds' => 1800, // stay "active" 30 min after last touch
    'fast_interval' => 60,     // while active: at most once per 60s
    'slow_interval' => 3600,   // while idle: at most once per hour (null = skip idle entirely)
],
```

Mark activity by updating `touch_at` when the wallet is used (GUI view, API call, unlock):

```php
$address->update(['touch_at' => now()]);
// or in bulk for a wallet:
$wallet->addresses()->update(['touch_at' => now()]);
```

Defaults (`fast_interval` 0, `slow_interval` null) preserve the legacy behavior: active
addresses sync every run, idle ones are skipped. `tron:address-sync --force` bypasses the schedule.

### Multiple Derivation Paths

The package uses BIP44 standard with the path `m/44'/195'/0'/0` for Tron:

```php
// Create addresses with different indexes from the same wallet
$address0 = Tron::createAddress($wallet, 'Address 0', 0);
$address1 = Tron::createAddress($wallet, 'Address 1', 1);
$address2 = Tron::createAddress($wallet, 'Address 2', 2);
```

## Testing

The package includes a comprehensive test suite:

```bash
# Run all tests
composer test

# Run with detailed output
vendor/bin/phpunit --testdox

# Run specific test
vendor/bin/phpunit --filter MnemonicTest
```

**Test Coverage:**

- ✅ BIP39 mnemonic generation (12, 15, 24 words)
- ✅ BIP39 mnemonic validation
- ✅ Seed generation with/without passphrase
- ✅ BIP44 address derivation
- ✅ Private/public key generation
- ✅ Address generation from private keys
- ✅ Multiple address generation

See [tests/README.md](tests/README.md) for more details.

## Artisan Commands

### Synchronization Commands

```bash
# Sync everything (all nodes, wallets, addresses)
php artisan tron:sync

# Sync specific node
php artisan tron:sync-node {nodeId}

# Sync specific wallet
php artisan tron:sync-wallet {walletId}

# Sync specific address
php artisan tron:sync-address {addressId}
```

### Creation Commands

```bash
# Create a new Tron node
php artisan tron:new-node

# Create a new wallet
php artisan tron:new-wallet

# Generate a new address
php artisan tron:new-address

# Import watch-only address
php artisan tron:import-address

# Register TRC-20 token
php artisan tron:new-trc20
```

## Security

### Best Practices

- 🔐 **Never store mnemonics in plain text** - Always encrypt sensitive data
- 🔑 **Use strong passphrases** - Add an additional layer of security to wallets
- 🌐 **Use HTTPS** - Always communicate with Tron nodes over HTTPS
- 🔒 **Secure your database** - Encrypt database backups and use strong credentials
- 👥 **Limit access** - Restrict who can access wallet operations
- 📝 **Log transactions** - Keep audit logs of all cryptocurrency operations
- 🧪 **Test on testnet first** - Always test on Shasta testnet before mainnet

### Encrypted Storage

The package automatically encrypts sensitive data:

- Private keys are encrypted using Laravel's encryption
- Mnemonics are encrypted before storage
- Passwords are hashed using bcrypt

### Reporting Vulnerabilities

If you discover a security vulnerability, please email [info@it-healer.com](mailto:info@it-healer.com). All security vulnerabilities will be promptly addressed.

## Troubleshooting

### Common Issues

**Issue: "Class 'GMP' not found"**
```bash
# Install GMP extension
# Ubuntu/Debian
sudo apt-get install php-gmp

# macOS (Homebrew)
brew install gmp
```

**Issue: "Invalid mnemonic phrase"**
- Ensure the mnemonic has the correct number of words (12, 15, 18, 21, or 24)
- Check for typos in the mnemonic words
- Verify words are from the BIP39 wordlist

**Issue: "Insufficient energy"**
- Freeze TRX to get energy
- Or use TRX to pay for energy (higher cost)
- Consider using `transferPreview()` to estimate costs

**Issue: "Node API limit exceeded"**
- TronGrid free tier has rate limits
- Upgrade to a paid plan on TronGrid
- Or use your own Tron node

### Debug Mode

Enable debug logging in `.env`:

```env
LOG_LEVEL=debug
```

Check logs in `storage/logs/laravel.log` for detailed information.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Support

Need help? Reach out to us:

- 💬 **Telegram:** [@biodynamist](https://t.me/biodynamist)
- 📱 **WhatsApp:** [+905516294716](https://wa.me/905516294716)
- 🌐 **Website:** [it-healer.com](https://it-healer.com)
- 📧 **Email:** [info@it-healer.com](mailto:info@it-healer.com)
- 🐛 **Issues:** [GitHub Issues](https://github.com/it-healer/laravel-tron/issues)

## Credits

- [IT-HEALER](https://github.com/it-healer)
- Built with ❤️ using [Laravel](https://laravel.com)
- Powered by [TronGrid](https://www.trongrid.io)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

---

<p align="center">
    Made with ❤️ by <a href="https://it-healer.com">IT-HEALER</a>
</p>
