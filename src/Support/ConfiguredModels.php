<?php

namespace Karnoweb\Wallet\Support;

use Karnoweb\Wallet\Models\Wallet;
use Karnoweb\Wallet\Models\WalletAllocation;
use Karnoweb\Wallet\Models\WalletCredit;
use Karnoweb\Wallet\Models\WalletCreditScope;
use Karnoweb\Wallet\Models\WalletOperation;
use Karnoweb\Wallet\Models\WalletTransaction;

/**
 * Central resolver for every model class the package persists.
 *
 * Services and models MUST use this helper (or read config directly)
 * instead of hard-coding the package's own model classes, so a host
 * application can transparently swap in its own subclasses via
 * `config('wallet.models.*')`.
 */
class ConfiguredModels
{
    public static function wallet(): string
    {
        return config('wallet.models.wallet', Wallet::class);
    }

    public static function operation(): string
    {
        return config('wallet.models.operation', WalletOperation::class);
    }

    public static function transaction(): string
    {
        return config('wallet.models.transaction', WalletTransaction::class);
    }

    public static function credit(): string
    {
        return config('wallet.models.credit', WalletCredit::class);
    }

    public static function creditScope(): string
    {
        return config('wallet.models.credit_scope', WalletCreditScope::class);
    }

    public static function allocation(): string
    {
        return config('wallet.models.allocation', WalletAllocation::class);
    }

    public static function newWallet(): Wallet
    {
        $class = static::wallet();

        return new $class();
    }

    public static function newOperation(): WalletOperation
    {
        $class = static::operation();

        return new $class();
    }

    public static function newTransaction(): WalletTransaction
    {
        $class = static::transaction();

        return new $class();
    }

    public static function newCredit(): WalletCredit
    {
        $class = static::credit();

        return new $class();
    }

    public static function newCreditScope(): WalletCreditScope
    {
        $class = static::creditScope();

        return new $class();
    }

    public static function newAllocation(): WalletAllocation
    {
        $class = static::allocation();

        return new $class();
    }
}
