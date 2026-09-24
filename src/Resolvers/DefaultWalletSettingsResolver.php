<?php

namespace Karnoweb\Wallet\Resolvers;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Contracts\WalletSettingsResolver;

/**
 * Merges owner-level settings using this exact priority, highest first:
 *
 * 1. Explicit operation option
 * 2. Owner model `walletSettings()`
 * 3. `config('wallet.owners.<exact model class>')`
 * 4. `config('wallet.defaults')`
 */
class DefaultWalletSettingsResolver implements WalletSettingsResolver
{
    /**
     * Setting keys that may be overridden by a per-operation option using
     * the same key name.
     */
    protected array $overridableKeys = [
        'auto_create',
        'allow_negative',
        'cash_withdrawable_default',
        'club_required',
        'idempotency_required',
        'credit_selection_strategy',
    ];

    public function resolve(?Model $owner = null, array $operationOptions = []): array
    {
        $settings = config('wallet.defaults', []);

        if ($owner !== null) {
            $ownerClass = get_class($owner);
            $ownerConfig = config("wallet.owners.{$ownerClass}", []);
            $settings = array_merge($settings, $ownerConfig);

            if (method_exists($owner, 'walletSettings')) {
                $settings = array_merge($settings, $owner->walletSettings());
            }
        }

        foreach ($this->overridableKeys as $key) {
            if (array_key_exists($key, $operationOptions)) {
                $settings[$key] = $operationOptions[$key];
            }
        }

        return $settings;
    }
}
