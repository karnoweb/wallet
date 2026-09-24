← [← Events & Accounting](12-events-and-accounting.md) | [Documentation Index](../README.md#documentation) | [Next: Concurrency →](14-concurrency.md)

# Expiration

Two separate ideas:

| Concept | Meaning |
|---------|---------|
| **Expired credit** | `expires_at` is in the past → **not eligible** for spend |
| **Expiration processing** | Artisan command applies `expire_action` (`burn` / `return`) and clears remaining |

## Eligibility vs nominal balance (Scenario 10)

```text
A = 300,000  expired
B = 500,000  active (future expiry)
C = 700,000  no expiration

Nominal balance = 1,500,000
Usable / spendable = 1,200,000  (B + C)
```

Payment **800,000** skips A:

```text
A = 300,000 (untouched)
B = 0
C = 400,000
Nominal = 700,000
Usable  = 400,000
```

```php
$user->charge(300_000, [
    'rules' => [
        'expires_at' => now()->subDay(),
        'expire_action' => 'burn',
    ],
]);
$user->charge(500_000, [
    'rules' => ['expires_at' => now()->addMonth()],
]);
$user->charge(700_000);

$user->balance();            // 1500000
$user->spendableBalance();   // 1200000

$user->pay(800_000);
$user->balance();            // 700000
```

A further payment of 500,000 fails with `InsufficientBalance` while A still holds 300,000 expired remaining.

## Expire actions

Set on the credit via rules (`expire_action`):

| Value | Behavior when processed |
|-------|-------------------------|
| `none` | Due credits are skipped by the expire command |
| `burn` | Remaining burned (wallet decreases) |
| `return` | Remaining returned along source lineage when applicable |

## Command

```bash
php artisan wallet:expire-credits
```

- Selects credits with `expires_at <= now` and `remaining_amount > 0`
- Processes in chunks of `config('wallet.expiration.chunk_size')` (default 500)
- Idempotent: running twice does not double-burn
- Dispatches `WalletCreditExpired` after each successful process

Schedule example:

```php
$schedule->command('wallet:expire-credits')->hourly();
```

Until you run the command, expired remaining still sits in the credit and in ledger balance, but **not** in spendable eligibility.

Next: [Concurrency](14-concurrency.md)

← [← Events & Accounting](12-events-and-accounting.md) | [Documentation Index](../README.md#documentation) | [Next: Concurrency →](14-concurrency.md)
