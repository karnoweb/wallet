<?php

namespace Karnoweb\Wallet\Services;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Contracts\WalletSettingsResolver;

/**
 * Entry point used by every other service to obtain the effective,
 * merged owner-level settings for an operation. Delegates the actual
 * precedence logic to the bound `WalletSettingsResolver` implementation
 * so a host application may fully replace the merging strategy.
 */
class WalletSettingsService
{
    public function __construct(protected WalletSettingsResolver $resolver)
    {
    }

    public function resolve(?Model $owner = null, array $operationOptions = []): array
    {
        return $this->resolver->resolve($owner, $operationOptions);
    }

    public function get(?Model $owner, string $key, mixed $default = null, array $operationOptions = []): mixed
    {
        $settings = $this->resolve($owner, $operationOptions);

        return $settings[$key] ?? $default;
    }
}
