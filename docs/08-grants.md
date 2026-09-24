← [← Transfers](07-transfers.md) | [Documentation Index](../README.md#documentation) | [Next: Restrictions →](09-restrictions.md)

# Grants

A **grant** moves value like a transfer, but destination credit **rules** come from an explicit snapshot you pass—not from copying the source credit’s scopes.

## Charge vs Transfer vs Grant

| Operation | What it does | Money supply | Destination rules |
|-----------|--------------|--------------|-------------------|
| **Charge** | Creates new credit on **one** wallet | Increases that wallet’s balance | From `rules` option (or defaults) |
| **Transfer** | Moves value wallet → wallet | Global total unchanged | **Copied** from source credits |
| **Grant** | Moves value wallet → wallet | Global total unchanged | From **`CreditRules` argument** (snapshot) |

At the ledger row level, grant still writes transfer-typed transactions, but the shared `WalletOperation` has `type = grant` so idempotency and reporting can distinguish grants from plain transfers.

## API

```php
use Karnoweb\Wallet\DTOs\CreditRules;

// Organization funds a member with restricted credit
$organization->grantTo($member, 700_000, [
    'allowed_club_ids' => [1],
    'allowed_service_ids' => [10],
    'expires_at' => now()->addYear(),
    'expire_action' => 'return',
    'cash_withdrawable' => false,
]);

// Or with the DTO
$organization->grantTo($member, 700_000, new CreditRules(
    allowedClubIds: [1],
    cashWithdrawable: false,
));
```

Fourth argument is the usual options/context (`idempotency_key`, `description`, …).

```php
$organization->grantTo($member, 700_000, [
    'cash_withdrawable' => false,
], [
    'idempotency_key' => 'grant-2026-01',
]);
```

Returns `WalletOperationResult` (source + destination transactions + operation).

## Rule snapshot

Rules are frozen when the grant succeeds. Changing organization configuration later does **not** alter credits already granted.

Important: destination scopes come from the **supplied rules**, not from the source credit’s scopes. Source credits are only used as the pool of value to consume (unscoped allocation on the source side, same as transfer).

## Lineage

Same as transfer: each destination credit gets `parent_credit_id` and `source_wallet_id` pointing at the consumed source credit / wallet.

## Event

`WalletCreditGranted` — source transaction, destination transaction, destination credits.

## Exceptions

Same family as transfers (`InvalidArgumentException`, `WalletException` for self-grant, `InsufficientBalance`, `IdempotencyConflict`).

Next: [Restrictions](09-restrictions.md)

← [← Transfers](07-transfers.md) | [Documentation Index](../README.md#documentation) | [Next: Restrictions →](09-restrictions.md)
