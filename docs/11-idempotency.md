← [← Organizational Credit](10-organizational-credit.md) | [Documentation Index](../README.md#documentation) | [Next: Events & Accounting →](12-events-and-accounting.md)

# Idempotency

Repeating the **same** financial request must not apply the effect twice.

## Example

```text
Balance = 1,000,000

Payment = 300,000
Key     = payment-001
→ Balance = 700,000

Retry same Payment + Key
→ Balance still 700,000  (not 400,000)
```

```php
$user->charge(1_000_000);

$tx1 = $user->pay(300_000, ['idempotency_key' => 'payment-001']);
$tx2 = $user->pay(300_000, ['idempotency_key' => 'payment-001']);

$tx1->id === $tx2->id; // true — replay
$user->balance();      // 700000
```

## How to send the key

Use `idempotency_key` in the options array for charge, pay, deduct, transfer, grant, and refund:

```php
$user->pay(300_000, ['idempotency_key' => 'payment-001']);
$user->refund($payment, 100_000, ['idempotency_key' => 'refund-001']);
$ali->transferTo($reza, 400_000, ['idempotency_key' => 'xfer-001']);
```

## Key scope

Keys are unique per **operation type** (payment, charge, refund, transfer, grant, deduct, …), not globally across all types. The same string may be reused for a different type.

Payload is hashed. Reusing a key with a **different** amount or critical fields throws `IdempotencyConflict`.

## When keys are required

If `idempotency_required` is true (config defaults, owner config, or `walletSettings()`), omitting the key throws `IdempotencyConflict`.

## Retry behavior

| Situation | Result |
|-----------|--------|
| Same key + same payload after success | Returns existing transaction(s); no new money movement |
| Same key + different payload | `IdempotencyConflict` |
| Concurrent same key | One writer wins; loser waits and reuses the completed operation |

## Failed / rolled-back operations

Idempotency rows are created **inside** the same database transaction as the financial writes. A rolled-back attempt does not leave a “success” that blocks a correct retry. Orphan/incomplete rows may be reclaimed on retry with the same key (package internal).

You do not need to call `IdempotencyService` yourself—use the public owner/facade API.

Next: [Events & Accounting](12-events-and-accounting.md)

← [← Organizational Credit](10-organizational-credit.md) | [Documentation Index](../README.md#documentation) | [Next: Events & Accounting →](12-events-and-accounting.md)
