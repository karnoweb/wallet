# Laravel Wallet & Credit Package — Implementation Blueprint for Cursor

**Document type:** Exact implementation specification  
**Target package:** `karnoweb/laravel-wallet`  
**Namespace:** `Karnoweb\Wallet`  
**Goal:** Cursor must implement this document as written. Do not redesign the domain, rename concepts, add abstractions, or remove requirements unless required to make the package compile.

---

# 1. Non-negotiable architecture

Implement these concepts exactly:

```text
Owner
  ↓
Wallet
  ↓
WalletTransaction
  ↓
WalletCredit
  ↓
WalletAllocation
```

Definitions:

- **Wallet** = container owned by a model. It is NOT owned by a club/branch.
- **WalletTransaction** = immutable financial movement. The club/branch of the operation belongs here.
- **WalletCredit** = source, remaining amount, lineage and rules of part of a wallet balance.
- **WalletAllocation** = immutable record showing which credit funded a debit transaction or which prior allocation was restored.
- **WalletOperation** = idempotency envelope shared by one or more wallet transactions.

Accounting is explicitly outside this package.

The package must expose enough information through events/DTOs for an application-level Accounting Integration to post documents.

Do not add a direct dependency on the accounting package.

---

# 2. Developer experience requirements

The package MUST support two equally valid usage styles.

## 2.1 Simple usage — Trait

A host model only needs:

```php
use Karnoweb\Wallet\Concerns\HasWallets;

class User extends Model
{
    use HasWallets;
}
```

Then the model must expose:

```php
$user->wallet();
$user->balance();
$user->spendableBalance();
$user->withdrawableBalance();

$user->charge(...);
$user->pay(...);
$user->deduct(...);
$user->transferTo(...);
$user->refund(...);
$user->grantTo(...);

$user->transactions();
$user->statement(...);
$user->summary(...);
```

A basic consumer must not need to understand `WalletCredit`, `WalletAllocation`, strategies, resolvers or events.

Those are internal unless advanced usage requires them.

## 2.2 Advanced usage — Facade

Provide a Laravel Facade named:

```php
Karnoweb\Wallet\Facades\Wallet
```

Usage:

```php
Wallet::for($user)->balance();

Wallet::for($user)->charge(1_000_000, [
    'club_id' => 1,
]);

Wallet::for($user)->pay(250_000, [
    'club_id' => 2,
    'transactionable' => $order,
]);
```

The object returned by `Wallet::for($owner)` is `WalletOwnerManager`.

It may operate on an explicit wallet instance (for example after resolving `$user->wallet()`):

```php
Wallet::for($user)->using($wallet)->pay(...);
```

Also support direct manager resolution:

```php
app(WalletManager::class);
```

---

# 3. Configuration precedence

Every owner-level setting must resolve with this exact priority, from highest to lowest:

```text
1. Explicit operation option
2. Owner model walletSettings()
3. config('wallet.owners.<exact model class>')
4. config('wallet.defaults')
```

The Trait must provide:

```php
public function walletSettings(): array
{
    return [];
}
```

A host model may override it:

```php
public function walletSettings(): array
{
    return [
        'allow_negative' => false,
        'auto_create' => true,
        'credit_selection_strategy' => \Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy::class,
        'cash_withdrawable_default' => true,
        'club_required' => true,
        'idempotency_required' => true,
    ];
}
```

Do NOT copy owner configuration into database rows unless the value is a snapshot rule of a specific credit.

---

# 4. Package file structure

Create exactly this high-level structure:

```text
config/
  wallet.php

database/
  migrations/
    create_wallets_table.php
    create_wallet_operations_table.php
    create_wallet_transactions_table.php
    create_wallet_credits_table.php
    create_wallet_credit_scopes_table.php
    create_wallet_allocations_table.php

src/
  WalletServiceProvider.php

  Facades/
    Wallet.php

  Concerns/
    HasWallets.php

  Contracts/
    ClubResolver.php
    CauserResolver.php
    CreditSelectionStrategy.php
    WalletSettingsResolver.php

  DTOs/
    WalletContext.php
    CreditRules.php
    SpendSegment.php
    WalletSummary.php
    WalletOperationResult.php

  Enums/
    WalletTransactionType.php
    WalletSign.php
    CreditExpireAction.php
    WalletAllocationType.php
    WalletOperationType.php

  Models/
    Wallet.php
    WalletOperation.php
    WalletTransaction.php
    WalletCredit.php
    WalletCreditScope.php
    WalletAllocation.php

  Services/
    WalletManager.php
    WalletOwnerManager.php
    WalletSettingsService.php
    BalanceService.php
    CreditService.php
    AllocationService.php
    ChargeService.php
    PaymentService.php
    DeductService.php
    TransferService.php
    RefundService.php
    GrantService.php
    ExpirationService.php
    WalletReportService.php
    IdempotencyService.php

  Strategies/
    FifoCreditSelectionStrategy.php

  Resolvers/
    DefaultClubResolver.php
    DefaultCauserResolver.php
    DefaultWalletSettingsResolver.php

  Events/
    WalletCharged.php
    WalletPaid.php
    WalletDeducted.php
    WalletTransferred.php
    WalletRefunded.php
    WalletCreditGranted.php
    WalletCreditExpired.php

  Exceptions/
    WalletException.php
    InsufficientBalance.php
    CreditNotEligible.php
    NegativeBalanceNotAllowed.php
    InvalidRefund.php
    IdempotencyConflict.php
    ClubRequired.php
    ImmutableWalletTransaction.php
    ImmutableWalletAllocation.php
    InvalidSpendSegments.php

  Console/
    ExpireCreditsCommand.php
    ReconcileWalletCommand.php

tests/
  Unit/
  Feature/
  Concurrency/
```

Do not add controllers or routes to the package.

---

# 5. Composer and Laravel registration

Create `composer.json` with:

- PSR-4: `Karnoweb\\Wallet\\` => `src/`
- dev PSR-4: `Karnoweb\\Wallet\\Tests\\` => `tests/`
- Laravel package auto-discovery for `WalletServiceProvider`
- PHP and Illuminate versions matching the accounting package/project compatibility matrix.
- Orchestra Testbench for package tests.

Do not require the accounting package.

---

# 6. Config file

Create `config/wallet.php`.

Required shape:

```php
return [

    'models' => [
        'wallet' => \Karnoweb\Wallet\Models\Wallet::class,
        'operation' => \Karnoweb\Wallet\Models\WalletOperation::class,
        'transaction' => \Karnoweb\Wallet\Models\WalletTransaction::class,
        'credit' => \Karnoweb\Wallet\Models\WalletCredit::class,
        'credit_scope' => \Karnoweb\Wallet\Models\WalletCreditScope::class,
        'allocation' => \Karnoweb\Wallet\Models\WalletAllocation::class,
    ],

    'defaults' => [
        'auto_create' => true,
        'allow_negative' => false,
        'cash_withdrawable_default' => true,
        'club_required' => false,
        'idempotency_required' => false,
        'credit_selection_strategy' => \Karnoweb\Wallet\Strategies\FifoCreditSelectionStrategy::class,
    ],

    'owners' => [
        // App\Models\User::class => [...]
    ],

    'resolvers' => [
        'club' => \Karnoweb\Wallet\Resolvers\DefaultClubResolver::class,
        'causer' => \Karnoweb\Wallet\Resolvers\DefaultCauserResolver::class,
        'settings' => \Karnoweb\Wallet\Resolvers\DefaultWalletSettingsResolver::class,
    ],

    'reports' => [
        'default_per_page' => 30,
        'max_per_page' => 200,
    ],

    'expiration' => [
        'chunk_size' => 500,
    ],
];
```

Requirements:

- Model classes MUST be replaceable from config.
- Services MUST resolve model class names from config and must not hard-code package model classes when creating records.
- Custom configured models must extend the package base model.
- Resolver implementations must be replaceable from config.
- No hard dependency on `App\Models\User`, Club, Organization, Contract or Service.

---

# 7. Database schema

## 7.1 `wallets`

Preserve the current structure as much as possible:

```text
id                 bigint unsigned PK
reference_type     varchar(255)
reference_id       bigint unsigned
club_id            bigint unsigned nullable   // legacy only, deprecated
extra_attributes   json/text nullable
deleted_at         timestamp nullable
created_at
updated_at
```

Indexes:

```text
(reference_type, reference_id)
club_id
```

Rules:

- One owner has exactly one wallet. There is no `primary` column.
- `club_id` is legacy-only.
- New package logic MUST NOT use `wallets.club_id`.
- Do not remove it in the first production migration.
- New installations may create it nullable and mark it deprecated in comments/docs.

Do not use `ON DELETE CASCADE` from owner models.

## 7.2 `wallet_operations`

Create:

```text
id                  bigint unsigned PK
type                varchar(32)
idempotency_key     varchar(128) nullable
payload_hash        char(64) nullable
created_at
updated_at
```

Unique:

```text
(type, idempotency_key)
```

Important:

- Multiple transactions may belong to one operation.
- If `idempotency_key` is null, the operation is still allowed when configuration does not require it.
- Duplicate key + same payload hash => return previous operation result.
- Duplicate key + different payload hash => throw `IdempotencyConflict`.

## 7.3 `wallet_transactions`

Preserve existing columns:

```text
id
wallet_id
causer_id
transaction_id
amount
sign
type
transactionable_type
transactionable_id
description
created_at
updated_at
```

Add:

```text
operation_id        bigint unsigned nullable
club_id             bigint unsigned nullable
```

Rules:

- `amount` remains unsigned integer.
- `sign` remains `1` or `-1`.
- `type` remains string-compatible with existing values.
- `causer_id` should be nullable for system/background operations.
- `transaction_id` remains nullable external reference.
- `transactionable_*` should be nullable in the generic package. If omitted, the operation may be the internal reference.
- Do not create foreign keys to host application tables such as `users`, `clubs` or `transactions`.
- `wallet_id` must use RESTRICT/NO ACTION behavior, never CASCADE.
- `operation_id` must reference `wallet_operations`.
- `club_id` has an index but no host-table FK.

Indexes:

```text
wallet_id
operation_id
club_id
causer_id
transaction_id
type
sign
(transactionable_type, transactionable_id)
(wallet_id, created_at, id)
```

## 7.4 `wallet_credits`

```text
id
wallet_id
source_transaction_id
parent_credit_id nullable
source_wallet_id nullable

original_amount bigint unsigned
remaining_amount bigint unsigned

starts_at timestamp nullable
expires_at timestamp nullable

expire_action varchar(20) default 'none'
cash_withdrawable boolean default true

created_at
updated_at
```

Indexes:

```text
wallet_id
source_transaction_id
parent_credit_id
source_wallet_id
(wallet_id, created_at, id)
(wallet_id, expires_at)
```

Foreign keys only to package-owned tables and use RESTRICT/NO ACTION for financial history.

## 7.5 `wallet_credit_scopes`

```text
id
wallet_credit_id
scope_type varchar(50)
scope_id bigint unsigned
created_at
```

Unique:

```text
(wallet_credit_id, scope_type, scope_id)
```

V1 scope types:

```text
club
service
```

Do not make foreign keys to host Club/Service tables.

## 7.6 `wallet_allocations`

```text
id
wallet_transaction_id
wallet_credit_id
amount bigint unsigned
type varchar(20)
original_allocation_id nullable
segment_key varchar(191) nullable
created_at
```

Types:

```text
consume
restore
```

Indexes:

```text
wallet_transaction_id
wallet_credit_id
original_allocation_id
segment_key
(wallet_transaction_id, segment_key)
```

`segment_key` is opaque to the package and enables item-level eligibility/refunds in advanced order use cases.

Allocation rows are immutable.

---

# 8. Enums

Implement string-backed enums:

## `WalletTransactionType`

```text
charge
payment
transfer
deduct
refund
system
```

## `WalletSign`

```text
Credit = 1
Debit = -1
```

## `CreditExpireAction`

```text
none
return
burn
```

## `WalletAllocationType`

```text
consume
restore
```

## `WalletOperationType`

```text
charge
payment
transfer
deduct
refund
grant
expiration
```

Models should cast enum-compatible fields while preserving database strings.

---

# 9. Models

## 9.1 Wallet

Relations:

```php
reference(): MorphTo
transactions(): HasMany
credits(): HasMany
```

Helpers:

```php
balance(): int
spendableBalance(array|WalletContext $context = []): int
withdrawableBalance(array|WalletContext $context = []): int
```

These delegate to services; do not duplicate financial logic in the model.

## 9.2 WalletTransaction

Relations:

```php
wallet()
operation()
transactionable(): MorphTo
allocations()
```

Rules:

- Model is immutable after creation.
- `updating` throws `ImmutableWalletTransaction`.
- `deleting` throws `ImmutableWalletTransaction`.
- Query-builder updates used only by explicit migration code are outside runtime API.

## 9.3 WalletCredit

Relations:

```php
wallet()
sourceTransaction()
parentCredit()
sourceWallet()
scopes()
allocations()
```

Only `remaining_amount` may be mutated by package runtime logic.

All other financial/source/rule fields are treated as snapshot fields.

## 9.4 WalletAllocation

Relations:

```php
transaction()
credit()
originalAllocation()
restores()
```

Updating/deleting throws `ImmutableWalletAllocation`.

---

# 10. Trait — `HasWallets`

One owner has exactly one wallet. Do **not** expose a public `wallets(): MorphMany` multi-wallet surface.

On owner `created`, when `auto_create=true`, the Trait MUST create the wallet immediately via `WalletManager` (same race-safe path as `wallet(true)`). Lazy `wallet(true)` remains a safety net only.

Implement these exact public methods:

```php
public function wallet(bool $create = true): ?Wallet;

public function balance(?Wallet $wallet = null): int;

public function spendableBalance(
    array|WalletContext $context = [],
    ?Wallet $wallet = null
): int;

public function withdrawableBalance(
    array|WalletContext $context = [],
    ?Wallet $wallet = null
): int;

public function charge(
    int $amount,
    array|WalletContext $options = [],
    ?Wallet $wallet = null
): WalletTransaction;

public function pay(
    int $amount,
    array|WalletContext $options = [],
    ?Wallet $wallet = null
): WalletTransaction;

public function deduct(
    int $amount,
    array|WalletContext $options = [],
    ?Wallet $wallet = null
): WalletTransaction;

public function transferTo(
    Model|Wallet $destination,
    int $amount,
    array|WalletContext $options = [],
    ?Wallet $sourceWallet = null
): WalletOperationResult;

public function refund(
    WalletTransaction $payment,
    ?int $amount = null,
    array $options = []
): WalletTransaction;

public function grantTo(
    Model|Wallet $destination,
    int $amount,
    array|CreditRules $rules = [],
    array|WalletContext $options = [],
    ?Wallet $sourceWallet = null
): WalletOperationResult;

public function transactions(?Wallet $wallet = null): Builder;

public function statement(
    array $filters = [],
    ?Wallet $wallet = null
): LengthAwarePaginator;

public function summary(
    array $filters = [],
    ?Wallet $wallet = null
): WalletSummary;

public function walletSettings(): array;
```

Every operation method MUST delegate to the Facade/Manager. The Trait must contain no duplicate transaction logic.

### Wallet creation

**On owner `created` (Trait boot):**

1. Resolve effective settings for the owner.
2. If `auto_create=false`, do nothing.
3. Otherwise call the same race-safe create path as `wallet(true)`.

**`wallet(true)` safety net:**

1. Start DB transaction.
2. Lock the owner row using its model primary key.
3. Re-query existing wallet for that owner.
4. If found return it.
5. If not found and `auto_create=true`, create one.
6. Commit.

This prevents two concurrent requests from creating two wallets for the same owner.

---

# 11. Facade and managers

## 11.1 Facade

Facade accessor resolves `WalletManager`.

## 11.2 `WalletManager`

Required methods:

```php
public function for(Model $owner): WalletOwnerManager;

public function wallet(Wallet $wallet): WalletOwnerManager;
```

`for()` does not immediately create a wallet. With default settings, creation happens on owner `created` via `HasWallets`; `wallet(true)` is the race-safe safety net when a wallet is still missing.

## 11.3 `WalletOwnerManager`

Maintain:

```text
owner
selectedWallet nullable
```

Required fluent methods:

```php
using(Wallet $wallet): self

wallet(bool $create = true): ?Wallet

balance(): int
spendableBalance(array|WalletContext $context = []): int
withdrawableBalance(array|WalletContext $context = []): int

charge(...)
pay(...)
deduct(...)
transferTo(...)
refund(...)
grantTo(...)

transactions(array $filters = []): Builder
statement(array $filters = []): LengthAwarePaginator
summary(array $filters = []): WalletSummary
```

It delegates to specialized services.

Do not put all domain logic in the manager.

---

# 12. Resolvers

## ClubResolver

```php
public function resolve(?Model $owner = null, array $options = []): ?int;
```

Default resolution order:

```text
options['club_id']
then null
```

Do not reference host helpers such as `current_club_id`.

Host application can replace resolver in config.

If effective settings say `club_required=true` and club cannot be resolved, throw `ClubRequired`.

## CauserResolver

```php
public function resolve(?Model $owner = null, array $options = []): ?int;
```

Default:

```text
options['causer_id']
auth()->id() when available
null
```

No hard FK is required.

## WalletSettingsResolver

Returns the final merged settings using the precedence defined earlier.

---

# 13. DTOs

## WalletContext

Immutable DTO with at least:

```text
clubId
causerId
transactionId
transactionable
description
idempotencyKey
occurredAt
scopes
segments
metadata
```

Provide:

```php
WalletContext::fromArray(array $data): self
```

Unknown keys must remain in `metadata` only if explicitly intended; otherwise ignore or validate consistently.

## CreditRules

Fields:

```text
allowedClubIds
allowedServiceIds
startsAt
expiresAt
expireAction
cashWithdrawable
```

Provide `fromArray()`.

## SpendSegment

Fields:

```text
key nullable
amount
scopes
```

Example:

```php
[
    'key' => 'order-item:1001',
    'amount' => 300_000,
    'scopes' => [
        'service' => [10],
    ],
]
```

Validation:

- Amount > 0.
- If segments exist, sum(segment.amount) MUST equal requested payment amount.
- Segment key should be unique inside one operation.
- When segments are absent, create one implicit segment.

## WalletSummary

At minimum:

```text
balance
spendable_balance nullable
withdrawable_balance
charges
payments
deducts
refunds
transfers_in
transfers_out
from
to
```

---

# 14. Credit eligibility

A credit is eligible for a payment segment only when all conditions pass:

1. `remaining_amount > 0`
2. `starts_at` is null or <= operation time
3. `expires_at` is null or > operation time
4. If the credit has `club` scopes, the operation club is one of them.
5. If the credit has `service` scopes, the segment contains at least one allowed service scope.
6. For cash withdrawal, `cash_withdrawable=true`.

Absence of scopes of a type means unrestricted for that type.

Do not infer rules from Organization/Contract models at spend time. Rules are snapshots on credits.

---

# 15. FIFO strategy

Implement `FifoCreditSelectionStrategy`.

Order eligible credits by:

```text
created_at ASC
id ASC
```

The strategy receives already-filtered candidate credits and amount required.

It returns planned allocations but MUST NOT mutate DB itself.

Locking and mutation belong to `AllocationService`.

This keeps strategy replaceable.

---

# 16. Balance behavior

## Total balance

The externally visible wallet balance is still based on effective wallet transactions:

```text
SUM(amount * sign)
```

For this package V1, a wallet transaction created successfully by a package operation is considered effective/final.

If a host application has a separate pending/published transaction lifecycle, it must invoke wallet mutation only when that host transaction becomes effective/published.

`transaction_id` remains an optional external reference.

Do not create a second pending-state engine inside this package.

## Spendable balance

Calculate from currently eligible credit `remaining_amount` for the supplied context/segments.

## Withdrawable balance

Sum current remaining credits with `cash_withdrawable=true` and valid dates.

If owner allows negative balance, report methods may expose configured credit limit later, but V1 does not invent synthetic credits.

---

# 17. Charge flow

Inside one DB transaction:

1. Validate amount > 0.
2. Resolve owner wallet.
3. Resolve settings/context.
4. Resolve/create `WalletOperation` through idempotency service.
5. Create positive WalletTransaction:
   - sign = +1
   - type = `charge`
   - club_id = context club
6. Create WalletCredit:
   - wallet_id
   - source_transaction_id = transaction id
   - original_amount = amount
   - remaining_amount = amount
   - no parent
   - no restrictions unless explicit CreditRules were passed by advanced API
   - `cash_withdrawable` from rule or effective default
7. Commit.
8. Dispatch `WalletCharged` after commit.
9. Return transaction.

A normal simple `charge()` creates an unrestricted credit.

---

# 18. Payment flow

Inside one DB transaction:

1. Validate amount and spend segments.
2. Resolve wallet/context/settings.
3. Resolve idempotent operation.
4. Determine eligible credits for each segment.
5. Lock required credit rows using `lockForUpdate()`.
6. Re-check eligibility and `remaining_amount` after lock.
7. Allocate FIFO.
8. If total eligible credit is insufficient:
   - if negative not allowed => throw `InsufficientBalance`
   - if negative allowed => V1 may create the debit transaction for the uncovered portion with no credit allocation; do NOT create fake credit.
9. Create one debit WalletTransaction:
   - sign = -1
   - type = `payment`
10. Create immutable consume allocations, including `segment_key`.
11. Decrease each credit `remaining_amount`.
12. Commit.
13. Dispatch `WalletPaid` after commit.

For mixed-service orders, the application SHOULD provide segments corresponding to independently refundable/eligible order lines.

---

# 19. Deduct / cash withdrawal flow

Same as Payment except:

- type = `deduct`
- only credits where `cash_withdrawable=true` may be selected.
- if eligible withdrawable amount is insufficient, fail unless a future explicit policy says otherwise.
- negative balance does NOT automatically make restricted organizational credits cashable.

Dispatch `WalletDeducted`.

---

# 20. Transfer flow

Transfer is one atomic `WalletOperation`.

Steps:

1. Resolve source and destination wallets.
2. Reject same wallet unless explicitly needed; V1 should reject.
3. Allocate source amount from eligible credits using FIFO.
4. Create source transaction:
   - type `transfer`
   - sign -1
5. Create destination transaction:
   - type `transfer`
   - sign +1
6. For each source allocation, create destination credit preserving lineage:
   - `parent_credit_id` = source credit id
   - `source_wallet_id` = source wallet id
   - original/remaining amount = transferred allocation amount
7. For normal transfer, copy existing credit restrictions by default.
8. Create source consume allocations.
9. Commit.
10. Dispatch one `WalletTransferred` event carrying both transactions and lineage.

Do not flatten credits from different source clubs into one destination credit.

---

# 21. Grant flow

Grant is transfer + rule snapshot.

Steps:

1. Perform source credit allocation exactly like transfer.
2. Create source debit and destination credit transaction.
3. For each source allocation, create one destination credit.
4. Preserve:
   - `parent_credit_id`
   - `source_wallet_id`
5. Replace/calculate destination credit rules using the supplied `CreditRules`.
6. Create `wallet_credit_scopes` snapshots.
7. Dispatch `WalletCreditGranted`.

Organization and Contract models are not known by the package.

Any model using the Trait can be a source.

---

# 22. Refund flow

Input must be an original `payment` transaction.

Validation:

- payment belongs to the same wallet being refunded.
- requested refund > 0.
- cumulative prior restores/refunds cannot exceed original payment allocations.
- optional segment filters must refer to segment keys in original allocations.

Default partial refund rule:

```text
Restore original consume allocations in original allocation id order.
```

Steps:

1. Determine remaining refundable amount per original allocation:
   ```text
   consumed - prior restores
   ```
2. Build restore plan.
3. Create positive WalletTransaction type `refund`.
4. Create `restore` allocations:
   - same credit id
   - `original_allocation_id` set
   - preserve segment_key
5. Increase credit `remaining_amount`.
6. Commit.
7. Dispatch `WalletRefunded`.

Refund does not require `cash_withdrawable=true`.

Refund never creates unrestricted money.

---

# 23. Expiration flow

Provide `ExpirationService` and command:

```text
php artisan wallet:expire-credits
```

Find credits:

```text
expires_at <= now
remaining_amount > 0
```

Process in chunks.

Lock each credit before action.

## burn

Create debit `system` transaction and consume allocation for the full remaining amount.

## return

Return to source wallet.

Preferred behavior:

- If the credit has a valid parent credit, restore to the parent lineage through a restore allocation/reference where possible.
- The receiving wallet gets the value back without losing original source lineage.
- If no parent/source wallet exists, throw/log a domain exception and do not silently burn.

Every expiration operation must support deterministic idempotency, for example:

```text
expiration:<credit_id>:<expires_at timestamp>
```

Dispatch `WalletCreditExpired`.

Running the command twice must not duplicate effects.

---

# 24. Idempotency service

`IdempotencyService` responsibilities:

1. Normalize operation payload.
2. Create SHA-256 payload hash.
3. If no key:
   - if required => throw validation/domain exception.
   - otherwise create normal operation row.
4. If key exists:
   - attempt insert unique `(type, key)`.
   - if duplicate, load existing operation.
   - compare payload hashes.
   - mismatch => `IdempotencyConflict`
   - match => return prior result by reading wallet transactions with operation_id.

Do not include timestamps generated by the server in payload hash.

Include financial inputs such as owner/wallet ids, amount, destination, rules, segment keys and external references.

---

# 25. Reporting

`WalletReportService` must provide:

## Statement

Filters:

```text
from
to
type / types
club_id / club_ids
sign
transactionable_type
transactionable_id
causer_id
min_amount
max_amount
per_page
```

Sort default:

```text
created_at DESC, id DESC
```

Return Laravel paginator.

## Summary

Aggregate using WalletTransaction rows, not Allocation rows.

Return `WalletSummary`.

## Credit report

Advanced service may expose:

```php
credits(Wallet $wallet, array $filters = []): Builder
allocations(WalletTransaction $transaction): Collection
```

Trait only needs common statement/summary APIs.

---

# 26. Accounting integration event payload

Events for debit operations must expose enough information to calculate inter-branch settlement.

For each allocation expose:

```text
amount
segment_key
credit_id
credit.source_transaction_id
credit.source_transaction.club_id
credit.parent_credit_id
credit.source_wallet_id
```

The accounting adapter can therefore determine:

```text
operation club
source credit club(s)
allocated amount per source club
```

Wallet package MUST NOT decide Debit/Credit accounting accounts.

---

# 27. Events and after-commit rule

All public financial events must be dispatched only after successful DB commit.

Use a Laravel-compatible after-commit approach.

Never dispatch a financial integration event before the transaction is durable.

Events must carry IDs/models required by integrations without recalculating mutable business rules.

---

# 28. Service provider

`WalletServiceProvider` must:

1. Merge package config.
2. Publish config with tag:
   ```text
   wallet-config
   ```
3. Publish migrations with tag:
   ```text
   wallet-migrations
   ```
4. Register package migrations.
5. Bind all resolver contracts using config classes.
6. Bind `CreditSelectionStrategy` from effective package default.
7. Register `WalletManager` as singleton.
8. Register Facade accessor.
9. Register console commands when running in console.

Do not read host application models during provider boot.

---

# 29. Configurable models

All relations and record creation must use configured model classes.

Example:

```php
$walletClass = config('wallet.models.wallet');
$wallet = new $walletClass();
```

For Eloquent relations in models/traits, resolve class dynamically from config.

Document host override:

```php
class CustomWallet extends \Karnoweb\Wallet\Models\Wallet
{
    // custom casts/scopes/relations
}
```

```php
'models' => [
    'wallet' => App\Models\CustomWallet::class,
]
```

---

# 30. Legacy production migration plan

The current production schema has one wallet per owner per club.

Implement migration support in host application in these phases.

Do NOT write a destructive one-shot migration.

## Phase A

- Install package tables/additive columns.
- Add nullable `wallet_transactions.club_id`.
- Add operation/credit/allocation tables.
- Keep `wallets.club_id`.

## Phase B

Backfill historical transaction club:

```text
wallet_transactions.club_id = wallet.club_id
```

Do not reconstruct one year of historical credit lots.

## Phase C — Opening credits

For each legacy wallet, calculate current effective balance.

If balance > 0, create an opening system transaction/credit representing that wallet’s remaining value and original club.

The opening credit MUST preserve the legacy source club.

If balance < 0, do not create fake positive credit. Preserve negative balance through transaction reconciliation and explicit migration handling.

## Phase D — Canonical wallet

For each `(reference_type, reference_id)` select canonical wallet:

1. Prefer the single remaining wallet if only one exists.
2. Otherwise lowest wallet id.

Move/repoint legacy wallet transactions and opening credits to canonical wallet only after auditing every external wallet_id reference.

Maintain temporary mapping:

```text
old_wallet_id
new_wallet_id
```

in host migration tooling if external tables reference wallet ids.

## Phase E

New writes go only to canonical/global wallet.

Legacy `wallets.club_id` becomes read-only/deprecated.

## Phase F reconciliation

For every owner:

```text
old combined balance == new canonical wallet balance
```

Also verify:

- transaction count
- historical amounts
- historical club attribution
- accounting totals
- opening credit totals

## Phase G

Remove application reads of `wallets.club_id`.

Drop the legacy column only in a later release.

---

# 31. Immutability and delete safety

Financial history must survive owner lifecycle.

Do not ship these destructive constraints:

```text
wallet_transactions.wallet_id ON DELETE CASCADE
wallet_transactions.causer_id ON DELETE CASCADE
```

Package-owned financial models should not be hard-deleted through normal runtime APIs.

Wallet may remain SoftDeletes-compatible, but deleting it must not remove financial rows.

---

# 32. Reconciliation command

Create:

```text
php artisan wallet:reconcile {wallet?}
```

It checks:

```text
transaction_balance = SUM(transaction amount * sign)
credit_available = SUM(active credit remaining_amount)
allocation-derived remaining for every credit
```

Checks:

```text
credit.remaining_amount ==
credit.original_amount
- consume allocations
+ restore allocations
```

Report mismatches with IDs and expected/current amounts.

Command must not auto-fix by default.

Optional `--json` output is acceptable.

---

# 33. Performance requirements

Avoid N+1 queries.

Payment should:

- fetch only eligible wallet credits.
- eager load scopes needed for eligibility.
- lock selected candidate rows.
- process allocations in bulk where practical.

Required indexes are listed in schema section.

Balance query must use SQL aggregate and must not load all transactions into PHP.

Reports must paginate.

Expiration must chunk.

---

# 34. Error behavior

Use domain exceptions, not generic `Exception`.

At minimum:

- `InsufficientBalance`
- `NegativeBalanceNotAllowed`
- `InvalidRefund`
- `IdempotencyConflict`
- `ClubRequired`
- `InvalidSpendSegments`

Services must throw; they do not return `false`.

---

# 35. What NOT to implement in V1

Do not add:

- generic rule engine
- workflow engine
- DSL
- HTTP controllers/routes
- accounting package dependency
- Club/Organization/Contract models
- Product/Service models
- Redis as source of truth
- event sourcing framework
- CQRS framework
- automatic historical replay of all legacy transactions
- user-selectable strategy UI

Keep the implementation conventional Laravel.

---

# 36. Cursor implementation order

Cursor must implement in this exact sequence and keep tests green after each stage:

1. Composer/package skeleton + ServiceProvider.
2. Config + model override support.
3. Enums + exceptions.
4. Migrations.
5. Models + relations + immutability guards.
6. Resolver contracts/default resolvers.
7. DTOs.
8. Settings service.
9. Trait boot (`created`) + wallet creation.
10. Facade + WalletManager + WalletOwnerManager.
11. Idempotency service.
12. Credit selection strategy.
13. Allocation/Credit services.
14. Balance service.
15. Charge.
16. Payment.
17. Deduct.
18. Transfer.
19. Grant.
20. Refund.
21. Expiration.
22. Reports.
23. Events + after-commit dispatch.
24. Console commands.
25. Full tests from `TEST-SCENARIOS.md`.
26. Documentation examples from `USAGE.md`.
27. Run static analysis/test suite and fix only implementation defects, not architecture.

---

# 37. Definition of done

The package is complete only when all are true:

- A model can add one Trait and immediately have wallet functionality.
- No club-specific wallet is created.
- Host model can override settings with `walletSettings()`.
- All model classes are overridable from config.
- Resolvers are overridable from config.
- Facade supports the full API.
- Basic consumer never needs to use Credit/Allocation directly.
- Credits preserve source transaction and source club.
- Transfers/Grants preserve credit lineage.
- Service restrictions work on segmented payments.
- Refund restores the original credit and segment.
- Cash-withdrawal restriction works.
- Expiry burn/return creates real transactions.
- Idempotency prevents duplicate financial effects.
- Row locking prevents double spend.
- Financial transactions/allocations are immutable.
- No financial history is cascade-deleted.
- Accounting can derive source club amounts from event payload.
- Legacy migration strategy is documented and test-covered.
- `composer test` / PHPUnit suite is fully green.
