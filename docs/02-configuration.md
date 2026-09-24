← [← Installation](01-installation.md) | [Documentation Index](../README.md#documentation) | [Next: Getting Started →](03-getting-started.md)

# Configuration

All keys live in `config/wallet.php` (publish with `--tag=wallet-config`). Only keys that exist in the package are documented here.

## Summary

| Section | Keys | Default / type |
|---------|------|----------------|
| `models.*` | Eloquent class overrides | Package model classes |
| `defaults.*` | Lowest-priority settings | See below |
| `owners` | Per-owner-class overrides | `[]` |
| `resolvers.*` | Container-bound contracts | Package resolvers |
| `reports.*` | Pagination limits | `30` / `200` |
| `expiration.chunk_size` | Expire command batch size | `500` |

There is **no** accounting config section. Accounting belongs to your application (or another package) via [events](12-events-and-accounting.md).

---

## Models

Override any model. Custom classes **must** extend the package base model so relations and casts keep working.

| Key | Default | Purpose | When to override |
|-----|---------|---------|------------------|
| `models.wallet` | `Karnoweb\Wallet\Models\Wallet` | Wallet row | Add soft deletes, observers, etc. |
| `models.operation` | `…\WalletOperation` | Idempotency envelope | Rare |
| `models.transaction` | `…\WalletTransaction` | Ledger event | Rare |
| `models.credit` | `…\WalletCredit` | Credit slice | Rare |
| `models.credit_scope` | `…\WalletCreditScope` | Club/service scope rows | Rare |
| `models.allocation` | `…\WalletAllocation` | Consume/restore rows | Rare |

---

## Defaults

Lowest priority in the settings stack. Overridden by (in rising order):

1. `config('wallet.owners.{OwnerClass}')`
2. Owner `walletSettings()` method
3. Per-operation options (where applicable)

| Key | Default | Type / expected | Purpose | When to override |
|-----|---------|-----------------|---------|------------------|
| `defaults.auto_create` | `true` | `bool` | Create wallet on owner `created` | Disable for owners that should not get a wallet automatically |
| `defaults.allow_negative` | `false` | `bool` | Allow spend that would leave negative usable balance | Special accounting policies only |
| `defaults.cash_withdrawable_default` | `true` | `bool` | Default `cash_withdrawable` on new credits when not set in rules | Org grants that must not be cashable |
| `defaults.club_required` | `false` | `bool` | Require a resolvable `club_id` on operations | Multi-branch apps |
| `defaults.idempotency_required` | `false` | `bool` | Require `idempotency_key` on financial ops | APIs that retry |
| `defaults.credit_selection_strategy` | `FifoCreditSelectionStrategy` | Class implementing `CreditSelectionStrategy` | Order of credit consumption | Custom FIFO/expiry strategies |

---

## Owners

```php
'owners' => [
    \App\Models\Contract::class => [
        'allow_negative' => true,
    ],
],
```

| Key | Default | Purpose |
|-----|---------|---------|
| `owners` | `[]` | Map of owner FQCN → settings overrides (same keys as `defaults`) |

---

## Resolvers

Bound in `WalletServiceProvider` from these class names. Replace with your own implementations of the contracts.

| Key | Default | Contract | Purpose |
|-----|---------|----------|---------|
| `resolvers.club` | `DefaultClubResolver` | `ClubResolver` | Resolve `club_id` when not passed |
| `resolvers.causer` | `DefaultCauserResolver` | `CauserResolver` | Resolve acting user / system causer |
| `resolvers.settings` | `DefaultWalletSettingsResolver` | `WalletSettingsResolver` | Merge defaults → owners → `walletSettings()` |

---

## Reports

| Key | Default | Purpose | When to override |
|-----|---------|---------|------------------|
| `reports.default_per_page` | `30` | Default page size for `statement()` | UI needs |
| `reports.max_per_page` | `200` | Hard cap for `statement()` | Prevent huge pages |

---

## Expiration

| Key | Default | Purpose | When to override |
|-----|---------|---------|------------------|
| `expiration.chunk_size` | `500` | Credits processed per chunk in `wallet:expire-credits` | Large ledgers |

---

## Settings precedence (runtime)

```text
config defaults
  → config owners[OwnerClass]
  → $owner->walletSettings()
  → per-call options (context)
```

Example: force club on payments for one model:

```php
// config/wallet.php
'owners' => [
    \App\Models\Member::class => [
        'club_required' => true,
    ],
],
```

Or on the model:

```php
public function walletSettings(): array
{
    return ['idempotency_required' => true];
}
```

Next: [Getting Started](03-getting-started.md)

← [← Installation](01-installation.md) | [Documentation Index](../README.md#documentation) | [Next: Getting Started →](03-getting-started.md)
