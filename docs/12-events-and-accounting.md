← [← Idempotency](11-idempotency.md) | [Documentation Index](../README.md#documentation) | [Next: Expiration →](13-expiration.md)

# Events & Accounting

This package is **not** an accounting ledger package.

```text
Wallet package
  = operational balances, credits, spend, refund, transfer, grant, expire

Your Accounting package / app
  = journal entries, GL, financial statements
```

Integrate by listening to domain events after successful commits.

## Events

| Event | When | Useful payload | Typical use |
|-------|------|----------------|-------------|
| `WalletCharged` | Charge committed | `transaction`, `credit` | Post cash-in / liability |
| `WalletPaid` | Payment committed | `transaction`, `allocations` | Revenue / AR settlement; allocations include credit lineage |
| `WalletDeducted` | Cash deduct committed | `transaction`, `allocations` | Cash out |
| `WalletRefunded` | Refund committed | `transaction`, `originalPayment`, `restoreAllocations` | Reverse prior posting |
| `WalletTransferred` | Transfer committed | `sourceTransaction`, `destinationTransaction`, `sourceAllocations`, `destinationCredits` | Inter-wallet / inter-branch |
| `WalletCreditGranted` | Grant committed | `sourceTransaction`, `destinationTransaction`, `destinationCredits` | Org → member funding |
| `WalletCreditExpired` | Expire processed | `credit`, `transaction` (nullable), `action` (`burn`/`return`) | Write off or return |

All live under `Karnoweb\Wallet\Events\…`.

## Listener example

```php
use Illuminate\Support\Facades\Event;
use Karnoweb\Wallet\Events\WalletPaid;

Event::listen(WalletPaid::class, function (WalletPaid $event) {
    $payment = $event->transaction;
    $allocations = $event->allocations;

    // Post journals using allocation → credit → source_wallet_id / club scopes
});
```

Or a dedicated listener class registered in your `EventServiceProvider` / `AppServiceProvider`.

## What the wallet will not do

- Create accounting accounts or fiscal years
- Post double-entry journals
- Know your chart of accounts

There is **no** `config/wallet.php` accounting section by design.

## Reconciliation (operational, not GL)

```bash
php artisan wallet:reconcile
php artisan wallet:reconcile 42 --json
```

Checks credit remaining vs consume/restore and transaction Σ vs credit totals. Read-only; does not auto-fix.

Next: [Expiration](13-expiration.md)

← [← Idempotency](11-idempotency.md) | [Documentation Index](../README.md#documentation) | [Next: Expiration →](13-expiration.md)
