<?php

namespace Karnoweb\Wallet\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Returns the final, merged owner-level settings using the package's
 * configuration precedence: operation option > owner model
 * `walletSettings()` > `config('wallet.owners.<class>')` >
 * `config('wallet.defaults')`.
 */
interface WalletSettingsResolver
{
    public function resolve(?Model $owner = null, array $operationOptions = []): array;
}
