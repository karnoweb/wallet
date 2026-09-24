<?php

namespace Karnoweb\Wallet;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Wallet\Console\ExpireCreditsCommand;
use Karnoweb\Wallet\Console\ReconcileWalletCommand;
use Karnoweb\Wallet\Contracts\CauserResolver;
use Karnoweb\Wallet\Contracts\ClubResolver;
use Karnoweb\Wallet\Contracts\CreditSelectionStrategy;
use Karnoweb\Wallet\Contracts\WalletSettingsResolver;
use Karnoweb\Wallet\Services\WalletManager;

class WalletServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/wallet.php', 'wallet');

        // Resolvers are bound from configured classes so a host
        // application can fully replace them without touching package
        // code. The provider never reads host application models here.
        $this->app->bind(ClubResolver::class, fn ($app) => $app->make(
            $app['config']->get('wallet.resolvers.club')
        ));

        $this->app->bind(CauserResolver::class, fn ($app) => $app->make(
            $app['config']->get('wallet.resolvers.causer')
        ));

        $this->app->bind(WalletSettingsResolver::class, fn ($app) => $app->make(
            $app['config']->get('wallet.resolvers.settings')
        ));

        $this->app->bind(CreditSelectionStrategy::class, fn ($app) => $app->make(
            $app['config']->get('wallet.defaults.credit_selection_strategy')
        ));

        $this->app->singleton(WalletManager::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/wallet.php' => $this->app->configPath('wallet.php'),
            ], 'wallet-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'wallet-migrations');

            $this->commands([
                ExpireCreditsCommand::class,
                ReconcileWalletCommand::class,
            ]);
        }
    }
}
