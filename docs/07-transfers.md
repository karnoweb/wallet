← [← Refunds](06-refunds.md) | [Documentation Index](../README.md#documentation) | [Next: Grants →](08-grants.md)

# Transfers

Move value from one wallet to another. Transfers **preserve** total value in the system; they do not create money.

## Example (Scenario 08)

```text
Ali  = 1,000,000
Reza =   200,000
Global = 1,200,000

Transfer Ali → Reza = 400,000

Ali  = 600,000
Reza = 600,000
Global = 1,200,000  (unchanged)
```

```php
$ali->charge(1_000_000);
$reza->charge(200_000);

$result = $ali->transferTo($reza, 400_000);

$result->sourceTransaction;      // debit on Ali
$result->destinationTransaction; // credit on Reza
$result->operation;              // shared WalletOperation (type=transfer)
```

Destination may be an owner model (`HasWallets`) or a `Wallet` instance.

## Lineage

For each source credit consumed, the package creates **one** destination credit with:

| Field | Meaning |
|-------|---------|
| `parent_credit_id` | The source credit that was consumed |
| `source_wallet_id` | The source wallet |

Expiry, expire action, `cash_withdrawable`, and club/service **scopes are copied** from the source credit. Credits from different source clubs are never flattened into a single destination credit.

## Not money creation

```text
Σ balances before = Σ balances after
```

Use charge/grant from an funded org wallet when you need to **introduce** organizational value into a member wallet (see [Grants](08-grants.md) and [Organizational Credit](10-organizational-credit.md)).

## Options

```php
$ali->transferTo($reza, 400_000, [
    'idempotency_key' => 'xfer-ali-reza-1',
    'description' => 'Gift',
]);
```

Transfer allocation on the source side is **unscoped** for club/service (any remaining credit may be moved); restrictions travel with the destination credits.

## Lock ordering

Source and destination wallet rows are locked in **ascending primary-key order**. Concurrent opposite transfers (`A→B` and `B→A`) cannot deadlock on wallet locks. See [Concurrency](14-concurrency.md).

## Exceptions

| Exception | When |
|-----------|------|
| `InvalidArgumentException` | Amount ≤ 0 |
| `WalletException` | Same wallet as destination |
| `InsufficientBalance` | Source lacks remaining value |
| `IdempotencyConflict` | Key/payload mismatch |

## Event

`WalletTransferred` — both transactions, source allocations, destination credits.

Next: [Grants](08-grants.md)

← [← Refunds](06-refunds.md) | [Documentation Index](../README.md#documentation) | [Next: Grants →](08-grants.md)
