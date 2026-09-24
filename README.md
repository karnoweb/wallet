# Laravel Wallet (`karnoweb/laravel-wallet`)

Owner-centric wallets with **credit lineage**, FIFO spending, transfers, grants, refunds, and expirations. Accounting-agnostic by design: the package manages operational balances; your app posts journals from events if needed.

**Namespace:** `Karnoweb\Wallet` · **Current release:** v13.2.0

## Features

- One wallet per owner model (`HasWallets` trait)
- Multiple credits per wallet (sources of spendable value)
- FIFO credit consumption (configurable strategy)
- Club and service restrictions on credits
- Expiring credits + `wallet:expire-credits` command
- Refunds that restore original credit sources (not unrestricted new money)
- Wallet-to-wallet transfers with credit lineage
- Organizational grants with rule snapshots
- Idempotent financial operations
- Concurrent spend / refund / transfer protection
- Domain events for accounting integration
- Read-only reconciliation: `wallet:reconcile`

## Requirements

| | |
|---|---|
| PHP | `^8.1` |
| Laravel / Illuminate | `^10.0` \| `^11.0` \| `^12.0` (`database`, `support`, `console`) |
| Databases | MySQL / MariaDB, PostgreSQL, SQLite (SQLite is fine for unit/feature/scenario tests; concurrency suite needs MySQL/Postgres) |

## Installation

```bash
composer require karnoweb/laravel-wallet
```

```bash
php artisan vendor:publish --tag=wallet-config
php artisan migrate
```

The service provider is auto-discovered. Full steps: [Installation](docs/01-installation.md).

## Quick Start

```php
use Karnoweb\Wallet\Concerns\HasWallets;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasWallets;
}
```

```php
$user->charge(1_000_000);

$user->balance();             // 1000000
$user->spendableBalance();    // usable for a given context
$user->withdrawableBalance(); // cash-withdrawable portion only

$payment = $user->pay(300_000);
$user->refund($payment, 100_000);

$user->balance(); // 800000
```

## Core Concepts

```text
Owner → Wallet → Credits → Transactions → Allocations
```

| Concept | Meaning |
|---------|---------|
| **Owner** | Any Eloquent model using `HasWallets` |
| **Wallet** | The owner's single money container |
| **Credit** | A slice of value with rules (expiry, club/service, cashable) |
| **Transaction** | An immutable financial event (charge, payment, …) |
| **Allocation** | Which credit funded (or was restored by) a transaction |
| **Operation** | Idempotency envelope for one logical request |

**Important:** `balance()` is the ledger net from transactions. It is **not** always fully spendable—restrictions and expiry reduce `spendableBalance()` / `withdrawableBalance()`.

## Common Operations

```php
// Charge (create credit)
$user->charge(1_000_000, [
    'rules' => [
        'allowed_club_ids' => [1],
        'expires_at' => now()->addMonth(),
        'cash_withdrawable' => true,
    ],
]);

// Pay (FIFO across eligible credits)
$user->pay(300_000, ['club_id' => 1, 'idempotency_key' => 'order-42']);

// Refund to original credits
$user->refund($payment);           // full remaining
$user->refund($payment, 100_000);  // partial

// Transfer (value-preserving)
$user->transferTo($otherUser, 400_000);

// Grant from organization with rule snapshot
$organization->grantTo($user, 700_000, [
    'allowed_service_ids' => [10],
    'cash_withdrawable' => false,
]);
```

## Documentation

| Guide | |
|-------|---|
| [Installation](docs/01-installation.md) | Requirements, publish, migrate |
| [Configuration](docs/02-configuration.md) | All `config/wallet.php` keys |
| [Getting Started](docs/03-getting-started.md) | Tutorial matching Scenario 01 |
| [Credits](docs/04-credits.md) | Multi-credit model & FIFO |
| [Payments](docs/05-payments.md) | Pay, eligibility, allocations |
| [Refunds](docs/06-refunds.md) | Restore order & refundable amount |
| [Transfers](docs/07-transfers.md) | Lineage & global conservation |
| [Grants](docs/08-grants.md) | Charge vs Transfer vs Grant |
| [Restrictions](docs/09-restrictions.md) | Club, service, cash, combined |
| [Organizational Credit](docs/10-organizational-credit.md) | Grants in practice |
| [Idempotency](docs/11-idempotency.md) | Safe retries |
| [Events & Accounting](docs/12-events-and-accounting.md) | Integration boundary |
| [Expiration](docs/13-expiration.md) | Eligibility vs processing |
| [Concurrency](docs/14-concurrency.md) | Double-spend / refund / transfer |
| [Testing](docs/15-testing.md) | Unit, Scenario, Concurrency suites |
| [Upgrading](docs/16-upgrading.md) | Upgrade checklist (incl. v13.2.0) |
| [API Reference](docs/17-api-reference.md) | Public operations quick reference |

Persian scenario walkthroughs (test docs): `tests/Scenarios/ScenarioNN_*.md`.

## Testing

```bash
composer test
# or
vendor/bin/phpunit

vendor/bin/phpunit --testsuite=Scenarios

# Concurrency (real row locking DB required)
# WALLET_TEST_CONCURRENCY_DSN=mysql://user:pass@127.0.0.1:3306/wallet_concurrency
vendor/bin/phpunit --testsuite=Concurrency
```

Details: [Testing](docs/15-testing.md).

## License

MIT — see [`LICENSE`](LICENSE).
