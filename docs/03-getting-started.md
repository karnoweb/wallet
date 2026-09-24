← [← Configuration](02-configuration.md) | [Documentation Index](../README.md#documentation) | [Next: Credits →](04-credits.md)

# Getting Started

A short tutorial using the public `HasWallets` API. Numbers match **Scenario 01** (first three steps).

## Mental model

```text
Owner
  └── Wallet
        ├── Credits          ← spendable slices of value
        ├── Transactions     ← immutable financial events
        └── Allocations      ← which credit funded which transaction
```

| Concept | Role |
|---------|------|
| **Owner** | Your Eloquent model (`User`, `Organization`, …) with `HasWallets` |
| **Wallet** | Single money container for that owner |
| **Credit** | A source of remaining value (with optional rules) |
| **Transaction** | Charge, payment, refund, transfer, deduct, … |
| **Allocation** | Links a spend/restore amount to a specific credit |
| **Operation** | Idempotency envelope wrapping one logical request |

`balance()` is the **ledger net** from transactions (`Σ amount × sign`). It is not always fully spendable: expiry and club/service rules reduce `spendableBalance()` / `withdrawableBalance()`.

## Setup

```php
use Karnoweb\Wallet\Concerns\HasWallets;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasWallets;
}
```

## Tutorial: charge → pay → refund

```text
Initial:   0
Charge:  +1,000,000
Payment:   -300,000
Refund:    +100,000
Final:     800,000
```

```php
$user = User::find(1);

// Start at zero
$user->balance(); // 0

// Charge creates a credit and a credit transaction
$user->charge(1_000_000);
$user->balance(); // 1000000

// Pay consumes eligible credits (FIFO by default)
$payment = $user->pay(300_000);
$user->balance(); // 700000

// Refund restores the same credit(s) that were spent — not a new unrestricted credit
$user->refund($payment, 100_000);
$user->balance(); // 800000
```

### After each step

| Step | Ledger balance | Credit remaining (single credit) |
|------|---------------:|---------------------------------:|
| Initial | 0 | — |
| Charge 1,000,000 | 1,000,000 | 1,000,000 |
| Pay 300,000 | 700,000 | 700,000 |
| Refund 100,000 | 800,000 | 800,000 |

## Optional context on every call

Most methods accept an options array (or `WalletContext`):

```php
$user->pay(300_000, [
    'club_id' => 1,
    'idempotency_key' => 'order-42-pay',
    'description' => 'Order #42',
    'scopes' => [
        'service' => [10], // service ids for eligibility
    ],
]);
```

Common option keys:

| Key | Used for |
|-----|----------|
| `club_id` | Club context / club-restricted credits |
| `causer_id` | Who initiated the operation |
| `idempotency_key` | Safe retries ([Idempotency](11-idempotency.md)) |
| `description` | Stored on the transaction |
| `scopes` | e.g. `['service' => […]]` for eligibility |
| `rules` | On **charge** only: credit restrictions snapshot |
| `metadata` | Passed through context (raw options) |

## Facade alternative

```php
use Karnoweb\Wallet\Facades\Wallet;

Wallet::for($user)->charge(1_000_000);
Wallet::for($user)->pay(300_000);
```

Same manager as the trait; prefer the trait on owner models for clarity.

## What to read next

- Multiple credits and FIFO → [Credits](04-credits.md)
- Payments in depth → [Payments](05-payments.md)
- How refund restoration works → [Refunds](06-refunds.md)

Next: [Credits](04-credits.md)

← [← Configuration](02-configuration.md) | [Documentation Index](../README.md#documentation) | [Next: Credits →](04-credits.md)
