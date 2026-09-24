← [← Expiration](13-expiration.md) | [Documentation Index](../README.md#documentation) | [Next: Testing →](15-testing.md)

# Concurrency

Financial operations run inside atomic DB transactions with row locks so concurrent requests cannot double-spend, over-refund, or deadlock on opposite transfers.

You do not call locking APIs yourself; use `HasWallets` / the `Wallet` facade.

## Double spend

```text
Balance = 100,000

Request A = 70,000
Request B = 70,000
```

Only one payment can succeed; the other gets `InsufficientBalance` (or equivalent failure). Credits are locked while allocating.

## Double refund

```text
Payment = 100,000

Refund A = 100,000
Refund B = 100,000
```

Only one full refund succeeds. The payment and its consume allocations are locked while computing refundable amounts.

## Partial concurrent refund

```text
Payment = 100,000

Refund A = 70,000
Refund B = 70,000
```

Successful restores cannot exceed **100,000** total. One request may succeed in full; the other succeeds only for the remaining refundable amount or fails with `InvalidRefund`.

## Opposite transfers

```text
A → B
B → A
```

Both wallets are locked in **ascending wallet id order** before work proceeds. That keeps `A→B` and `B→A` from deadlocking on wallet rows. Grants use the same ordering.

## Idempotency under concurrency

Same `idempotency_key` racing: one insert wins; the other waits briefly and reuses the completed operation instead of applying a second effect. See [Idempotency](11-idempotency.md).

## Testing concurrency

SQLite in-memory cannot prove real multi-connection locking. Use the Concurrency suite with MySQL/PostgreSQL:

```bash
# PowerShell
$env:WALLET_TEST_CONCURRENCY_DSN="mysql://root:@127.0.0.1:3306/wallet_concurrency"
vendor\bin\phpunit --testsuite=Concurrency
```

Details: [Testing](15-testing.md).

## Guarantees (summary)

| Risk | Package behavior |
|------|------------------|
| Two spends of the same credit | Serialized; second sees reduced remaining |
| Two refunds of the same payment | Serialized; refundable capped |
| Opposite transfers | Deterministic lock order |
| Duplicate retries | Idempotency keys |

Next: [Testing](15-testing.md)

← [← Expiration](13-expiration.md) | [Documentation Index](../README.md#documentation) | [Next: Testing →](15-testing.md)
