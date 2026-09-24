# Laravel Wallet & Credit — `karnoweb/laravel-wallet`

Owner-centric wallets with credit lineage, FIFO spending, transfers, grants, refunds and expirations. Accounting-agnostic by design.

- Namespace: `Karnoweb\Wallet`
- Requires: PHP `^8.1`, `illuminate/database`, `illuminate/support`, `illuminate/console` (`^10.0|^11.0|^12.0`)
- Supports Laravel 10, 11 and 12

## Concepts

```text
Owner → Wallet → WalletTransaction → WalletCredit → WalletAllocation
```

- **Wallet**: container owned by any Eloquent model (not by a club/branch).
- **WalletTransaction**: immutable financial movement (charge, payment, transfer, deduction, refund). The club/branch of the operation lives here.
- **WalletCredit**: where money came from, remaining amount, lineage and rules (expiry, withdrawability, scope).
- **WalletAllocation**: immutable record of which credit funded a debit (or which allocation was restored on refund).
- **WalletOperation**: idempotency envelope shared by one or more transactions.

Accounting is intentionally outside this package. The package exposes events and DTOs so the host application can post accounting documents.

## Installation

```bash
composer require karnoweb/laravel-wallet
```

Publish configuration:

```bash
php artisan vendor:publish --tag=wallet-config
```

Publish migrations (if the project manages package migrations manually):

```bash
php artisan vendor:publish --tag=wallet-migrations
```

Run migrations:

```bash
php artisan migrate
```

## Quick start

```php
use Karnoweb\Wallet\Concerns\HasWallets;

class User extends \Illuminate\Database\Eloquent\Model
{
    use HasWallets;
}
```

```php
$user->charge(10000);            // increase balance (creates credit)
$user->balance();                // current balance
$user->spendableBalance();       // balance usable for payments
$user->withdrawableBalance();    // cash-withdrawable portion

$user->pay(2500);                // spend with FIFO credit selection
$user->deduct(500);              // deduct without payment semantics
$user->transferTo($otherUser, 1000);
$user->refund($transactionId);   // restore consumed credits
$user->grantTo($otherUser, 1000, $rules); // scoped / expiring grant

$user->transactions();
$user->statement();
$user->summary();
```

Advanced usage — credit rules, expirations (`wallet:credits:expire`, `wallet:reconcile`), idempotency keys,
club/branch scoping, custom resolvers and strategies, reports and events — is covered in the docs below.

## Documentation

- [`wallet-package-USAGE.md`](wallet-package-USAGE.md) — developer usage guide
- [`wallet-package-IMPLEMENTATION.md`](wallet-package-IMPLEMENTATION.md) — implementation blueprint
- [`wallet-package-TEST-SCENARIOS.md`](wallet-package-TEST-SCENARIOS.md) — test scenarios
- [`CHANGELOG.md`](CHANGELOG.md) — release history

## Testing

Default suite (SQLite in-memory — Unit + Feature):

```bash
composer test
# or
vendor/bin/phpunit
```

Concurrency suite (MySQL / MariaDB / PostgreSQL only — real multi-process row locking):

```bash
# PowerShell
$env:WALLET_TEST_CONCURRENCY_DSN="mysql://root:@127.0.0.1:3306/wallet_concurrency"
vendor\bin\phpunit --testsuite=Concurrency

# bash
WALLET_TEST_CONCURRENCY_DSN=mysql://root:@127.0.0.1:3306/wallet_concurrency \
  vendor/bin/phpunit --testsuite=Concurrency
```

Without `WALLET_TEST_CONCURRENCY_DSN`, concurrency tests are skipped (SQLite cannot prove row-level locking across processes).

## License

MIT — see [`LICENSE`](LICENSE).
