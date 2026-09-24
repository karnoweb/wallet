← [← Payments](05-payments.md) | [Documentation Index](../README.md#documentation) | [Next: Transfers →](07-transfers.md)

# Refunds

A refund **restores** previously consumed credit allocations. It does **not** create a new unrestricted credit.

Public API:

```php
$user->refund($payment);              // full remaining refundable
$user->refund($payment, 100_000);     // partial
$user->refund($payment, 100_000, [
    'idempotency_key' => 'refund-42',
]);
```

`$payment` must be a **payment** transaction belonging to the owner’s wallet.

## Restoration contract (v13.1.0)

Partial refunds restore consume allocations in **original allocation id order** (the same order FIFO produced during payment)—**not** reverse order.

### Scenario 03 walkthrough

Initial credits:

```text
A = 300,000
B = 500,000
C = 700,000
```

Payment **600,000**:

```text
Allocations: A = 300,000, B = 300,000
Remaining:   A = 0, B = 200,000, C = 700,000
Balance:     900,000
```

Refund **200,000** → fills allocation A first:

```text
A = 200,000
B = 200,000
C = 700,000
Balance = 1,100,000
```

Refund **400,000** (rest of that payment):

```text
A = 300,000
B = 500,000
C = 700,000
Balance = 1,500,000
```

Credit count stays **3** (no new unrestricted row).

```php
$user->charge(300_000);
$user->charge(500_000);
$user->charge(700_000);

$payment = $user->pay(600_000);
$user->refund($payment, 200_000);
$user->refund($payment, 400_000);
```

## Partial and multiple refunds

- Each refund creates a refund transaction and **restore** allocations linked to original consume allocations.
- Maximum refundable = sum of consume amounts minus already restored amounts.
- Exceeding refundable throws `InvalidRefund`.

## Refund after transfer-derived credits

If a payment consumed credits that were created by a transfer/grant (with `parent_credit_id`), refund still restores **those destination credits**—not the remote source wallet. Lineage on the credit is unchanged; remaining increases on the same rows.

## Concurrent refund protection

Refundable calculation and restores run inside one DB transaction with row locks on the payment, its consume allocations, and related credits. Concurrent full/partial refunds cannot over-restore. See [Concurrency](14-concurrency.md).

## Exceptions

| Exception | When |
|-----------|------|
| `InvalidRefund` | Not a payment; wrong wallet; nothing to restore; amount too high; bad segment keys |
| `IdempotencyConflict` | Key reused with different payload |
| `InvalidArgumentException` | (via other paths) invalid amounts where applicable |

## Event

After commit: `WalletRefunded` with the refund transaction, original payment, and restore allocations.

Next: [Transfers](07-transfers.md)

← [← Payments](05-payments.md) | [Documentation Index](../README.md#documentation) | [Next: Transfers →](07-transfers.md)
