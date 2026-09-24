← [← Upgrading](16-upgrading.md) | [Documentation Index](../README.md#documentation) | [README](../README.md)

# API Reference

Quick reference for the **public** owner surface (`HasWallets`) and facade. Prefer these over calling internal services directly.

## Trait: `Karnoweb\Wallet\Concerns\HasWallets`

| Method | Purpose | Important arguments | Returns | Notable failures |
|--------|---------|---------------------|---------|------------------|
| `wallet(bool $create = true)` | Get/create wallet | `$create` | `?Wallet` | — |
| `balance(?Wallet $wallet = null)` | Ledger net | optional wallet | `int` | — |
| `spendableBalance($context = [], ?Wallet $wallet = null)` | Eligible remaining | context: club / scopes | `int` | — |
| `withdrawableBalance($context = [], ?Wallet $wallet = null)` | Cashable eligible remaining | context | `int` | — |
| `charge(int $amount, $options = [], ?Wallet $wallet = null)` | Create credit | `rules`, `idempotency_key`, … | `WalletTransaction` | `InvalidArgumentException`, `IdempotencyConflict`, `ClubRequired` |
| `pay(int $amount, $options = [], ?Wallet $wallet = null)` | Spend for goods/services | `club_id`, `scopes`, `idempotency_key` | `WalletTransaction` | `InsufficientBalance`, `ClubRequired`, `IdempotencyConflict` |
| `deduct(int $amount, $options = [], ?Wallet $wallet = null)` | Cash withdrawal | same + cashable credits only | `WalletTransaction` | `InsufficientBalance`, … |
| `refund(WalletTransaction $payment, ?int $amount = null, array $options = [])` | Restore allocations | null amount = full remaining | `WalletTransaction` | `InvalidRefund`, `IdempotencyConflict` |
| `transferTo(Model\|Wallet $destination, int $amount, $options = [], ?Wallet $sourceWallet = null)` | Move value | destination owner or wallet | `WalletOperationResult` | `InsufficientBalance`, `WalletException` |
| `grantTo(Model\|Wallet $destination, int $amount, $rules = [], $options = [], ?Wallet $sourceWallet = null)` | Move + rule snapshot | rules array or `CreditRules` | `WalletOperationResult` | same family as transfer |
| `transactions(?Wallet $wallet = null)` | Transaction query | — | `Builder` | — |
| `statement(array $filters = [], ?Wallet $wallet = null)` | Paginated statement | filters | `LengthAwarePaginator` | — |
| `summary(array $filters = [], ?Wallet $wallet = null)` | Aggregates | filters | `WalletSummary` | — |
| `walletSettings()` | Override defaults | return `array` | `array` | — |

`$options` may be `array` or `WalletContext`. Amounts are integers in the smallest currency unit your app uses (no floats).

## Facade: `Karnoweb\Wallet\Facades\Wallet`

```php
use Karnoweb\Wallet\Facades\Wallet;

Wallet::for($user)->pay(300_000);
Wallet::wallet($walletModel)->balance();
```

## Common context keys

| Key | Operations |
|-----|------------|
| `club_id` | Most spends / charges |
| `causer_id` | Audit |
| `idempotency_key` | All financial ops |
| `description` | Stored on transaction |
| `scopes` | `['service' => […]]` |
| `rules` | Charge only |
| `segments` | Advanced multi-segment pay/deduct |
| `occurred_at` | Backdated eligibility window |
| `transaction_id` / `transactionable` | Link to your domain model |

## Domain exceptions (`Karnoweb\Wallet\Exceptions`)

| Class | Typical cause |
|-------|----------------|
| `WalletException` | Base / domain invariant |
| `InsufficientBalance` | Not enough eligible remaining (also covers “no eligible credits”) |
| `InvalidRefund` | Bad refund target or amount |
| `IdempotencyConflict` | Key required / payload mismatch |
| `ClubRequired` | Club missing when required |
| `InvalidSpendSegments` | Segment amounts invalid |
| `ImmutableWalletTransaction` | Mutating a transaction |
| `ImmutableWalletAllocation` | Mutating an allocation |

Also defined (reserved / future use; not thrown by current service flows): `CreditNotEligible`, `NegativeBalanceNotAllowed`.

## Artisan

| Command | Purpose |
|---------|---------|
| `wallet:expire-credits` | Process due expirations |
| `wallet:reconcile {wallet?} {--json}` | Consistency check |

## Events

See [Events & Accounting](12-events-and-accounting.md).

← [← Upgrading](16-upgrading.md) | [Documentation Index](../README.md#documentation) | [README](../README.md)
