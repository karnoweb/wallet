<?php

namespace Karnoweb\Wallet\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Concerns\HasWallets;

/**
 * Fixture model whose `walletSettings()` override is controlled per test
 * via the static `$settingsOverride` property, to exercise the settings
 * precedence chain (blueprint section 3).
 */
class ModelWithSettings extends Model
{
    use HasWallets;

    protected $table = 'users';

    protected $fillable = ['name'];

    public static array $settingsOverride = [];

    public function walletSettings(): array
    {
        return static::$settingsOverride;
    }
}
