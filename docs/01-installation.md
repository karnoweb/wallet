← [README](../README.md) | [Documentation Index](../README.md#documentation) | [Next: Configuration →](02-configuration.md)

# Installation

How to install `karnoweb/laravel-wallet` into a Laravel application.

## Requirements

| Requirement | Constraint |
|-------------|------------|
| PHP | `^8.1` |
| Laravel / Illuminate | `illuminate/database`, `illuminate/support`, `illuminate/console` — `^10.0` \| `^11.0` \| `^12.0` |
| Database | MySQL / MariaDB, PostgreSQL, or SQLite |

SQLite is suitable for development and the package’s unit / feature / scenario tests. The **Concurrency** test suite requires a real row-locking database (MySQL/MariaDB or PostgreSQL). See [Testing](15-testing.md).

## Composer

```bash
composer require karnoweb/laravel-wallet
```

Package name on Packagist: `karnoweb/laravel-wallet`  
PHP namespace: `Karnoweb\Wallet`

## Service Provider

`Karnoweb\Wallet\WalletServiceProvider` is registered via Laravel package auto-discovery (`composer.json` → `extra.laravel.providers`).

Manual registration is **not** required unless you disable package discovery.

The `Wallet` facade alias (`Karnoweb\Wallet\Facades\Wallet`) is also auto-discovered.

## Publish config (optional)

```bash
php artisan vendor:publish --tag=wallet-config
```

This copies `config/wallet.php` into your application. If you skip publishing, the package merges its default config automatically.

## Migrations

Migrations ship with the package and are **loaded automatically** by the service provider (`loadMigrationsFrom`). Running:

```bash
php artisan migrate
```

is enough for most apps.

To copy migration files into your app (optional):

```bash
php artisan vendor:publish --tag=wallet-migrations
```

### Tables created

| Table | Purpose |
|-------|---------|
| `wallets` | One wallet per owner (morph) |
| `wallet_operations` | Idempotency envelopes |
| `wallet_transactions` | Immutable financial events |
| `wallet_credits` | Spendable value slices + lineage |
| `wallet_credit_scopes` | Club / service restrictions |
| `wallet_allocations` | Consume / restore links between transactions and credits |

Schema details stay in the migration files; you do not need to recreate them by hand.

## Model setup

Attach the trait to any Eloquent model that should own a wallet:

```php
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Concerns\HasWallets;

class User extends Model
{
    use HasWallets;
}
```

No interface is required. With default settings (`auto_create => true`), a wallet is created when the owner is created. Calling `$user->wallet()` (or any money operation) also creates it lazily if missing.

Optional owner-level overrides:

```php
public function walletSettings(): array
{
    return [
        'allow_negative' => false,
        'club_required' => true,
    ];
}
```

## Artisan commands (available after install)

| Command | Purpose |
|---------|---------|
| `php artisan wallet:expire-credits` | Process due credit expirations |
| `php artisan wallet:reconcile {wallet?} {--json}` | Read-only consistency check |

## Installation checklist

1. `composer require karnoweb/laravel-wallet`
2. (Optional) `php artisan vendor:publish --tag=wallet-config`
3. `php artisan migrate`
4. Add `HasWallets` to your owner model(s)
5. Verify: `$user->charge(1000); $user->balance();` returns `1000`

Next: [Configuration](02-configuration.md)

← [README](../README.md) | [Documentation Index](../README.md#documentation) | [Next: Configuration →](02-configuration.md)
