# Changelog

## v13.1.0 — 2026-09-24

Core hardening & correctness release. No new public features; financial
retry, concurrency and MySQL safety improvements only.

- Atomic idempotency: `WalletOperation` is claimed inside the same DB
  transaction as financial writes (retry-safe; no orphan operations)
- Concurrent payment / deduct / transfer / refund safety with row locks
  and deterministic lock ordering (`wallet_id` / credit `id` ASC)
- Refund availability calculation and restore execution are atomic
- MySQL: READ COMMITTED for wallet transactions; balance aggregate casts
  unsigned `amount` safely; shorter morph index name for MySQL limits
- Credit selection pushes club/service/date/remaining filters into SQL
- Hardening indexes migration (`000007`)
- Real multi-process concurrency suite (`WALLET_TEST_CONCURRENCY_DSN`)

## v13.0.0 — 2026-09-24

Initial public release of `karnoweb/laravel-wallet`.

- Owner-centric global wallets (`HasWallets` trait) for any Eloquent model
- Charge, pay, deduct, transfer, grant and refund operations
- Credit lineage with FIFO spending and scoped/expiring credits
- Immutable transactions and allocations, idempotency envelopes
- Club/branch scoping, configurable models, resolvers and strategies
- Reports, statements and summaries
- Artisan commands: `wallet:credits:expire`, `wallet:reconcile`
- Domain events for accounting integration (package stays accounting-agnostic)
- Laravel 10 / 11 / 12 support, PHP ^8.1
