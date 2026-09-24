# Changelog

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
