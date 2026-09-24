← [← Getting Started](03-getting-started.md) | [Documentation Index](../README.md#documentation) | [Next: Payments →](05-payments.md)

# Credits

A **credit** is a slice of wallet value with its own remaining amount and rules. A wallet almost always holds **multiple** credits over time (charges, grants, transfers, refunds restoring pieces).

## Why multiple credits?

Each charge, grant, or inbound transfer creates one or more credits. Spending does not merge them: payments allocate across credits (FIFO by default). That preserves:

- Expiration per credit
- Club / service restrictions
- Cash-withdrawability
- Lineage (`parent_credit_id`, `source_wallet_id`) for transfers/grants

## Credit fields (conceptual)

| Field | Meaning |
|-------|---------|
| `original_amount` | Amount when the credit was created |
| `remaining_amount` | Still available (only mutable monetary field) |
| Source transaction | The credit/transfer transaction that created it |
| `parent_credit_id` | Source credit when value moved via transfer/grant |
| `source_wallet_id` | Wallet the value came from (lineage) |
| `starts_at` / `expires_at` | Validity window |
| `expire_action` | `none`, `burn`, or `return` (see [Expiration](13-expiration.md)) |
| Club / service scopes | Rows in `wallet_credit_scopes` |
| `cash_withdrawable` | Eligible for `deduct()` cash withdrawal |

There is no free-form `metadata` column on credits in the current schema; use transaction `description` / context metadata for app-level notes.

## Creating credits: charge

```php
$user->charge(1_000_000);

$user->charge(500_000, [
    'rules' => [
        'allowed_club_ids' => [1],
        'allowed_service_ids' => [10],
        'expires_at' => now()->addDays(30),
        'expire_action' => 'burn', // none | burn | return
        'cash_withdrawable' => false,
        'starts_at' => now(),
    ],
]);
```

Rules are snapshotted at creation time. Changing organization config later does **not** rewrite existing credits.

## FIFO example (Scenario 02)

```text
Credit A = 300,000
Credit B = 500,000
Credit C = 700,000
Total    = 1,500,000
```

Payment of **600,000** (default FIFO = oldest credit first):

```text
A → 300,000  (fully consumed)
B → 300,000  (partial)

Remaining:
A = 0
B = 200,000
C = 700,000

Balance = 900,000
```

```php
$user->charge(300_000);
$user->charge(500_000);
$user->charge(700_000);

$user->pay(600_000);

$user->balance(); // 900000
```

## Balance vs remaining credits

- Ledger `balance()` ≈ sum of credit `remaining_amount` under normal operation (also verified by `wallet:reconcile`).
- **Usable** amount for a payment can be lower when credits are expired or restricted. Use `spendableBalance($context)` for the operation context.

## Credit selection strategy

Configured via `wallet.defaults.credit_selection_strategy` (default: `FifoCreditSelectionStrategy`). Host apps can bind another class implementing `CreditSelectionStrategy`.

## Immutability

Aside from `remaining_amount`, credit snapshot fields are immutable. Attempts to change them throw `WalletException`.

Next: [Payments](05-payments.md)

← [← Getting Started](03-getting-started.md) | [Documentation Index](../README.md#documentation) | [Next: Payments →](05-payments.md)
