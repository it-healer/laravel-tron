![Logo](docs/logo.jpeg)

<a href="https://packagist.org/packages/it-healer/laravel-tron" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/v/it-healer/laravel-tron.svg?style=flat&cacheSeconds=3600" alt="Latest Version on Packagist">
</a>

<a href="https://packagist.org/packages/it-healer/laravel-tron" target="_blank">
    <img style="display: inline-block; margin-top: 0.5em; margin-bottom: 0.5em" src="https://img.shields.io/packagist/dt/it-healer/laravel-tron.svg?style=flat&cacheSeconds=3600" alt="Total Downloads">
</a>

---

**Tron** is a Laravel package for work with cryptocurrency Tron, with the support TRC-20 tokens.It allows you to generate HD wallets using mnemonic phrase, validate addresses, get addresses balances and resources, preview and send TRX/TRC-20 tokens. You can automate the acceptance and withdrawal of cryptocurrency in your application.

## Requirements

The following versions of PHP are supported by this version.

* PHP 8.1 and older
* Laravel 10 or older
* PHP Extensions: Decimal, GMP, BCMath, CType.


## Installation
You can install the package via composer:
```bash
composer require it-healer/laravel-tron
```

After you can run installer using command:
```bash
php artisan tron:install
```

And run migrations:
```bash
php artisan migrate
```

Register Service Provider and Facade in app, edit `config/app.php`:
```php
'providers' => ServiceProvider::defaultProviders()->merge([
    ...,
    \ItHealer\LaravelTron\TronServiceProvider::class,
])->toArray(),

'aliases' => Facade::defaultAliases()->merge([
    ...,
    'Tron' => \ItHealer\LaravelTron\Facades\Tron::class,
])->toArray(),
```

For Laravel 10 you edit file `app/Console/Kernel` in method `schedule(Schedule $schedule)` add:
```php
$schedule->command('tron:sync')
    ->everyMinute()
    ->runInBackground();
```

or for Laravel 11+ add this content to `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tron:sync')
    ->everyMinute()
    ->runInBackground();
```

## How use?
1. Firstly register an account on the <a href="https://www.trongrid.io/register">TronGrid</a> website and get an <a href="https://www.trongrid.io/dashboard/keys">API key</a>.
2. Using the following code, create a node through which the library will work:
```php
$apiKey = "..."; // API Key from TronGrid.io
Tron::createTronGridNode($apiKey, 'node_name');
```
3. Now you can create Tron Wallet using code:
```php
$mnemonic = Tron::mnemonicGenerate();
echo 'Mnemonic: '.implode(' ', $mnemonic);

$wallet = Tron::createWallet('wallet_name', $mnemonic);
```
4. Create primary Tron Address in your Wallet using code:
```php
$address = Tron::createAddress($wallet, 'primary_address_name');

echo 'Primary Address: '.$address->address;
```
5. Now you can send TRX using this code:
```php
$to = 'receiver tron address';
$amount = 1;

$transfer = Tron::transfer($address, $to, $amount);

echo 'TXID: '.$transfer->txid;
```

### If you want work with TRC-20
#### For example: Tether USDT

1. You must create TronTRC20 model using this code:
```php
$contractAddress = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

$trc20Token = TronTRC20::create($contractAddress);
```
2. For transfer Tether USDT TRC-20 for another address you can use this code:
```php
$to = 'receiver tron address';
$amount = 1;

$transferTRC20 = Tron::transferTRC20($address, $to, $amount);

echo 'TXID: '.$transferTRC20->txid;
```


## Commands

Synchronizing everything
```bash
php artisan tron:sync
```

Node synchronization
```bash
php artisan tron:sync-node NODE_ID
```

Wallet synchronization
```bash
php artisan tron:sync-wallet WALLET_ID
```

Address synchronization
```bash
php artisan tron:sync-address ADDRESS_ID
```

Create Tron Node. Before you need register account in https://trongrid.io and generate API Key.
```bash
php artisan tron:new-node
```

Create Tron Wallet.
```bash
php artisan tron:new-wallet
```

Generate Tron Address.
```bash
php artisan tron:new-address
```

Import watch only address.
```bash
php artisan tron:import-address
```

Create TRC-20 Token
```bash
php artisan tron:new-trc20
```

## Support

- Telegram: [@biodynamist](https://t.me/biodynamist)
- WhatsApp: [+905516294716](https://wa.me/905516294716)
- Web: [it-healer.com](https://it-healer.com)

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [IT-HEALER](https://github.com/it-healer)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

