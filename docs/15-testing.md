← [← Concurrency](14-concurrency.md) | [Documentation Index](../README.md#documentation) | [Next: Upgrading →](16-upgrading.md)

# Testing

The package ships three layers of tests. Use them as a contract when integrating or upgrading.

## Unit / Feature

Independent behaviors (balances, restrictions, services, edge cases).

```bash
composer test
# or
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature
```

Default PHPUnit env (see `phpunit.xml`): SQLite in-memory.

## Scenario suite

End-to-end business paths. Each scenario has:

```text
tests/Scenarios/ScenarioNN…Test.php   ← executable assertions
tests/Scenarios/ScenarioNN_….md       ← Persian narrative (internal test docs)
```

| # | Focus |
|---|--------|
| 01 | Basic charge / pay / refund |
| 02 | Multi-credit FIFO payment |
| 03 | Multi-credit refund restoration order |
| 04 | Club restriction |
| 05 | Service restriction |
| 06 | Organization grant lifecycle |
| 07 | Non-cash-withdrawable credit |
| 08 | Transfer conservation |
| 09 | Transfer + refund |
| 10 | Expiration eligibility |
| 11 | Idempotency lifecycle |
| 12 | Failure rollback |
| 13 | Complex lifecycle |
| 14 | Combined club + service |
| 15 | Transaction / allocation integrity |

Run:

```bash
vendor/bin/phpunit --testsuite=Scenarios
```

The Persian `.md` files are **not** the English package user docs; they document the scenario test suite for maintainers.

## Concurrency suite

Requires a real row-locking database. Skipped when the DSN env is unset.

| Env | Purpose |
|-----|---------|
| `WALLET_TEST_CONCURRENCY_DSN` | Connection URL for the concurrency DB |

Examples (from the suite itself):

```bash
# PowerShell
$env:WALLET_TEST_CONCURRENCY_DSN="mysql://root:@127.0.0.1:3306/wallet_concurrency"
vendor\bin\phpunit --testsuite=Concurrency

# bash
WALLET_TEST_CONCURRENCY_DSN=mysql://root:@127.0.0.1:3306/wallet_concurrency \
  vendor/bin/phpunit --testsuite=Concurrency
```

PostgreSQL DSNs work the same way if your driver is configured. Create an empty database first; the suite migrates schema on that connection.

Workers are separate OS processes (`proc_open`), not fake sequential calls.

## Testing your integration

In your app tests:

```php
$user->charge(1_000_000);
$user->pay(300_000, ['idempotency_key' => 't1']);
$this->assertSame(700_000, $user->balance());
```

Prefer asserting balances and refundable amounts over reaching into internal services.

Next: [Upgrading](16-upgrading.md)

← [← Concurrency](14-concurrency.md) | [Documentation Index](../README.md#documentation) | [Next: Upgrading →](16-upgrading.md)
