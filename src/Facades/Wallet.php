<?php

namespace Karnoweb\Wallet\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Karnoweb\Wallet\Models\Wallet as WalletModel;
use Karnoweb\Wallet\Services\WalletManager;
use Karnoweb\Wallet\Services\WalletOwnerManager;

/**
 * @method static WalletOwnerManager for(Model $owner)
 * @method static WalletOwnerManager wallet(WalletModel $wallet)
 *
 * @see WalletManager
 */
class Wallet extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WalletManager::class;
    }
}
