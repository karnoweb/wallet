← [← Testing](15-testing.md) | [Documentation Index](../README.md#documentation) | [Next: API Reference →](17-api-reference.md)

# Upgrading

Practical checklist when moving to a newer `karnoweb/laravel-wallet` release. This is not a full changelog.

## Standard upgrade steps

1. **Composer**

   ```bash
   composer update karnoweb/laravel-wallet
   ```

2. **New migrations**  
   Package migrations load automatically. Run:

   ```bash
   php artisan migrate
   ```

   If you previously published migrations into `database/migrations`, compare new package files (including index/hardening migrations) and publish or copy any missing ones.

3. **Config**  
   Diff your published `config/wallet.php` against the package’s `config/wallet.php`. Add any new keys; remove obsolete ones only after confirming they no longer exist upstream.

4. **Clear caches** (when applicable)

   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

5. **Run tests**

   ```bash
   vendor/bin/phpunit
   vendor/bin/phpunit --testsuite=Scenarios
   ```

   Optionally run Concurrency with `WALLET_TEST_CONCURRENCY_DSN` set.

6. **Review breaking changes** for the target version (release notes / git tags).

---

## Upgrading to v13.2.0

v13.2.0 is a documentation and scenario-test release. Public financial API and
migrations are unchanged from v13.1.0.

1. `composer update karnoweb/laravel-wallet` (to `^13.2` or `13.2.0`)
2. No new migrations expected for this jump from 13.1.x
3. Prefer `README.md` + `docs/` over any removed root legacy markdown files
4. Optionally run `vendor/bin/phpunit --testsuite=Scenarios`

---

## Upgrading to v13.1.0

v13.1.0 focuses on correctness hardening, not a new product surface:

- Idempotency resolved **inside** the same DB transaction as financial writes
- Concurrent refund / spend locking
- Deterministic wallet lock ordering for transfer/grant
- Credit selection / balance query improvements (including safer aggregates on MySQL)
- Hardening indexes migration (`…_000007_add_wallet_hardening_indexes.php`)

### What you should do for 13.1.0

1. `composer update karnoweb/laravel-wallet` (to `^13.1` or `13.1.0`)
2. `php artisan migrate` so hardening indexes apply
3. Re-run Feature + Scenario suites
4. If you run parallel payment/refund traffic, run the Concurrency suite once against MySQL/Postgres
5. Confirm listeners still handle the same event classes (event names unchanged)

### Behavioral notes to re-verify in your app

- Refund restoration order: **allocation id order** (not reverse)—see [Refunds](06-refunds.md)
- Expired credits: still in nominal balance until processed; not spendable—see [Expiration](13-expiration.md)
- Public API (`HasWallets` methods) is unchanged in intent from 13.0.x

If you skipped 13.0.0, treat 13.0.0 → 13.1.0 as one jump: migrate all wallet tables from a clean install path or from your previous major’s schema.

Next: [API Reference](17-api-reference.md)

← [← Testing](15-testing.md) | [Documentation Index](../README.md#documentation) | [Next: API Reference →](17-api-reference.md)
