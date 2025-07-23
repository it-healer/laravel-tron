<?php

namespace ItHealer\LaravelTron;

use ItHealer\LaravelTron\Commands\NewTRC20Command;
use ItHealer\LaravelTron\Commands\NewWalletCommand;
use ItHealer\LaravelTron\Commands\NewAddressCommand;
use ItHealer\LaravelTron\Commands\ImportAddressCommand;
use ItHealer\LaravelTron\Commands\AddressSyncCommand;
use ItHealer\LaravelTron\Commands\NewNodeCommand;
use ItHealer\LaravelTron\Commands\NodeSyncCommand;
use ItHealer\LaravelTron\Commands\SyncCommand;
use ItHealer\LaravelTron\Commands\WalletSyncCommand;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TronServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('tron')
            ->hasConfigFile()
            ->hasMigrations([
                'create_tron_nodes_table',
                'create_tron_wallets_table',
                'create_tron_trc20_table',
                'create_tron_addresses_table',
                'create_tron_transactions_table',
                'create_tron_deposits_table',
            ])
            ->runsMigrations()
            ->hasCommands(
                NewNodeCommand::class,
                NewWalletCommand::class,
                NewAddressCommand::class,
                ImportAddressCommand::class,
                NewTRC20Command::class,
                SyncCommand::class,
                AddressSyncCommand::class,
                WalletSyncCommand::class,
                NodeSyncCommand::class,
            )
            ->hasInstallCommand(function(InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations();
            });

        $this->app->singleton(Tron::class);
    }
}
