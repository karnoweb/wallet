← [← Credits](04-credits.md) | [Documentation Index](../README.md#documentation) | [Next: Refunds →](06-refunds.md)

# Payments

Spend wallet value against eligible credits. Public API: `$owner->pay()`.

## Create a payment

```php
$payment = $user->pay(300_000);

$payment = $user->pay(300_000, [
    'club_id' => 1,
    'idempotency_key' => 'invoice-99',
    'description' => 'Invoice #99',
    'scopes' => [
        'service' => [10],
    ],
]);
```

Returns a `WalletTransaction` with `type = payment` (debit).

## Context that affects eligibility

| Input | Effect |
|-------|--------|
| `club_id` | Must match credits that have club scopes (unscoped credits always match) |
| `scopes.service` | Service ids for this spend segment |
| Credit `expires_at` | Expired credits are not eligible |
| Credit `starts_at` | Not yet started → not eligible |
| Strategy | Default FIFO among eligible credits |

Absence of club scopes on a credit = unrestricted for club. Same for service.

If `club_required` is enabled and no club can be resolved → `ClubRequired`.

## Single-credit payment

```php
$user->charge(1_000_000);
$payment = $user->pay(300_000);

$user->balance(); // 700000
```

One consume allocation on the single credit.

## Multi-credit payment (Scenario 02)

```php
$user->charge(300_000); // A
$user->charge(500_000); // B
$user->charge(700_000); // C

$payment = $user->pay(600_000);
```

FIFO allocation:

```text
A = 0
B = 200,000
C = 700,000
Balance = 900,000
```

## Insufficient balance

If eligible remaining value is too low:

```php
use Karnoweb\Wallet\Exceptions\InsufficientBalance;

try {
    $user->pay(999_999_999);
} catch (InsufficientBalance $e) {
    // requested vs available in message
}
```

Nothing is committed: credits and transactions stay unchanged (atomic transaction).

## Idempotency

```php
$user->pay(300_000, ['idempotency_key' => 'payment-001']);
$user->pay(300_000, ['idempotency_key' => 'payment-001']); // same transaction replayed
```

See [Idempotency](11-idempotency.md).

## Result & side effects

On success:

1. Debit `WalletTransaction` created
2. Consume `WalletAllocation` rows written
3. Credit `remaining_amount` reduced
4. `WalletPaid` event dispatched after commit (with transaction + allocations)

## Pay vs deduct

| | `pay()` | `deduct()` |
|--|---------|------------|
| Use | Goods/services payment | Cash withdrawal |
| Credits | Any eligible by club/service/expiry | Only `cash_withdrawable = true` |
| Event | `WalletPaid` | `WalletDeducted` |

```php
$user->deduct(50_000);
```

## Exceptions to handle

| Exception | When |
|-----------|------|
| `InvalidArgumentException` | Amount ≤ 0 |
| `InsufficientBalance` | Not enough eligible remaining |
| `ClubRequired` | Club required but missing |
| `IdempotencyConflict` | Same key, different payload |

Next: [Refunds](06-refunds.md)

← [← Credits](04-credits.md) | [Documentation Index](../README.md#documentation) | [Next: Refunds →](06-refunds.md)
