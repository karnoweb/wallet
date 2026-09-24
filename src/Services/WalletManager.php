<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Models\Wallet;

/**
 * Root entry point of the Facade/advanced API (`Wallet::for($owner)`,
 * `app(WalletManager::class)`). Registered as a singleton by
 * {@see \Karnoweb\Wallet\WalletServiceProvider}.
 */
class WalletManager
{
    /**
     * Does not immediately create a wallet by itself. With default
     * settings, `HasWallets` creates on owner `created`;
     * {@see WalletOwnerManager::wallet()} remains the race-safe safety net.
     */
    public function for(Model $owner): WalletOwnerManager
    {
        return new WalletOwnerManager($owner);
    }

    public function wallet(Wallet $wallet): WalletOwnerManager
    {
        $owner = $wallet->reference;

        return (new WalletOwnerManager($owner))->using($wallet);
    }
}
