← [← Grants](08-grants.md) | [Documentation Index](../README.md#documentation) | [Next: Organizational Credit →](10-organizational-credit.md)

# Restrictions

Credits may carry rules that shrink **usable** balance for a given operation. Ledger `balance()` can still include restricted or expired remaining amounts.

## Club restriction

Set at charge/grant time:

```php
$user->charge(500_000, [
    'rules' => ['allowed_club_ids' => [1]],
]);
```

Spend with matching club:

```php
$user->pay(100_000, ['club_id' => 1]); // eligible
$user->pay(100_000, ['club_id' => 2]); // this credit not eligible
```

Credits **without** club scopes are unrestricted for club. If the operation has no `club_id`, only unrestricted-for-club credits are eligible (see CreditService filters).

## Service restriction

```php
$user->charge(400_000, [
    'rules' => ['allowed_service_ids' => [10]], // e.g. swimming
]);

$user->pay(100_000, [
    'scopes' => ['service' => [10]],
]);
```

Credits without service scopes are unrestricted for service. A restricted credit requires **overlap** with the segment’s service ids.

## Expiration

```php
$user->charge(300_000, [
    'rules' => [
        'expires_at' => now()->subDay(), // already expired → not spendable
        'expire_action' => 'burn',
    ],
]);
```

Expired credits remain in nominal remaining until processed; they are **not** eligible for payment. Details: [Expiration](13-expiration.md).

## Cash withdrawable

```php
$user->charge(700_000, [
    'rules' => ['cash_withdrawable' => false],
]);

$user->pay(100_000);     // may still succeed (payment)
$user->deduct(100_000);  // InsufficientBalance if only non-cashable remains
```

`withdrawableBalance()` sums only cash-withdrawable eligible remaining.

## Combined restrictions (Scenario 14)

Eligibility is an **intersection**: every applicable restriction on a credit must pass.

Example credits:

| Credit | Amount | Club | Service |
|--------|-------:|------|---------|
| A | 300,000 | Club A | Swimming |
| B | 400,000 | Club A | (any) |
| C | 500,000 | (any) | Swimming |
| D | 600,000 | (any) | (any) |

Eligible totals:

| Context | Eligible credits | Amount |
|---------|------------------|-------:|
| Club A + Swimming | A, B, C, D | 1,800,000 |
| Club A + Gym | B, D | 1,000,000 |
| Club B + Swimming | C, D | 1,100,000 |
| Club B + Gym | D only | 600,000 |

Matching **only** club or **only** service is not enough when the credit has both.

```php
$user->pay(200_000, [
    'club_id' => 1,
    'scopes' => ['service' => [10]],
]);
```

## Spend segments (advanced)

Payments/deducts can split into segments with different scopes via `segments` in context. Most apps use a single segment built from top-level `scopes`. Invalid segment totals throw `InvalidSpendSegments`.

Next: [Organizational Credit](10-organizational-credit.md)

← [← Grants](08-grants.md) | [Documentation Index](../README.md#documentation) | [Next: Organizational Credit →](10-organizational-credit.md)
