# Laravel Wallet & Credit Package — Test Scenarios

**Purpose:** This file is the acceptance suite for implementation.  
**Rule:** Cursor must implement automated tests for every scenario below.  
**Expected style:** PHPUnit/Pest consistent with the package/accounting project.  
**Do not mark scenarios as complete unless assertions prove the expected financial state.**

---

# 1. Test conventions

For every financial operation, assert as applicable:

- transaction count
- transaction type
- sign
- amount
- wallet id
- club id
- operation id
- balance
- credit original amount
- credit remaining amount
- allocation amount/type
- source credit lineage
- idempotency behavior
- event dispatch after commit

Money is integer-based.

No floating-point assertions.

---

# 2. Basic wallet creation

## W001 — Wallet created with owner

**Given**
- User model uses `HasWallets`.
- `auto_create=true`.

**When**
- `User::create(...)` succeeds.

**Then**
- Exactly one wallet row exists for that owner.
- `reference_type` / `reference_id` match the user.
- `club_id` is null in new architecture.
- `$user->wallet(false)` returns that wallet.

## W002 — Wallet method resolves existing wallet

**Given**
- Owner already has a wallet from `created`.

**When**
- `$user->wallet()` is called.

**Then**
- The existing wallet is returned.
- No second wallet is created.

## W003 — auto_create disabled

**Given**
- `auto_create=false`.

**When**
- `User::create(...)` succeeds, then `$user->wallet(false)`.

**Then**
- No wallet row is created by the `created` hook.
- `wallet(false)` returns null.

## W004 — Concurrent wallet creation

**Given**
- User has no wallet (for example after `auto_create=false` create).

**When**
- Two concurrent requests call `wallet()`.

**Then**
- Exactly one wallet exists.
- Both calls resolve the same wallet.

---

# 3. Settings precedence

## S001 — Config defaults

**Given**
- config `allow_negative=false`.
- Model has no override.

**Then**
- resolved setting is false.

## S002 — Owner class config override

**Given**
- defaults false.
- `wallet.owners[Contract::class].allow_negative=true`.

**Then**
- Contract resolves true.

## S003 — Model override

**Given**
- owner config false.
- `walletSettings()` returns true.

**Then**
- resolved value is true.

## S004 — Operation override highest priority

**Given**
- defaults/model config differ.
- an allowed operation override is provided.

**Then**
- operation value wins.

---

# 4. Charge

## C001 — Basic charge

**Given**
- Empty wallet.

**When**
- Charge 1,000,000.

**Then**
- Balance = 1,000,000.
- One transaction:
  - type charge
  - sign +1
  - amount 1,000,000
- One credit:
  - original 1,000,000
  - remaining 1,000,000
  - source transaction is charge transaction.

## C002 — Charge with club

Charge 1,000,000 in Club 1.

Assert:

- transaction.club_id = 1
- source credit resolves source club = 1.

## C003 — Charge with transactionable

Provide an Order/Payment model as transactionable.

Assert morph reference is stored.

## C004 — Charge idempotent duplicate

Call same charge twice with same idempotency key and same payload.

Assert:

- one financial charge effect.
- one credit.
- second call returns original operation result.

## C005 — Charge idempotency conflict

Same key, different amount.

Assert `IdempotencyConflict`.

---

# 5. Basic balance

## B001 — Balance from transactions

Charge 1,000,000, pay 300,000.

Expected balance:

```text
700,000
```

## B002 — Balance SQL aggregate

Assert balance implementation uses DB aggregate behavior and does not require loading all transaction models.

---

# 6. Basic payment

## P001 — Full payment from one credit

Charge 1,000,000.

Pay 400,000.

Assert:

- wallet balance = 600,000
- credit remaining = 600,000
- payment transaction amount = 400,000
- sign = -1
- one consume allocation = 400,000.

## P002 — FIFO across two credits

Charge:

```text
Credit A 500,000 first
Credit B 500,000 second
```

Pay 700,000.

Expected:

```text
A remaining = 0
B remaining = 300,000

allocations:
A = 500,000
B = 200,000
```

## P003 — FIFO tie-break by id

Create credits with equal timestamp.

Assert lower id consumed first.

## P004 — Insufficient balance

Balance 500,000.

Pay 600,000 with negative disabled.

Assert:

- `InsufficientBalance`
- no payment transaction
- no allocation
- credit remaining unchanged.

## P005 — Atomic rollback

Force exception after allocation planning but before commit.

Assert all transaction/credit/allocation changes rolled back.

---

# 7. Branch/global-wallet behavior

## BR001 — Charge in Club 1, pay in Club 2

Charge 1,000,000 in Club 1.

Pay 300,000 in Club 2.

Assert:

- one global wallet.
- payment transaction club = 2.
- allocation source credit source club = 1.
- balance = 700,000.

## BR002 — Credits from multiple clubs

Charge:

```text
500,000 Club 1
300,000 Club 2
```

Pay:

```text
600,000 Club 3
```

Expected allocation:

```text
500,000 from Club 1
100,000 from Club 2
```

Remaining:

```text
Club 1 sourced credit = 0
Club 2 sourced credit = 200,000
```

## BR003 — No new wallet per club

Operate same User in 3 clubs.

Assert exactly one wallet.

---

# 8. Club requirement

## CR001 — Club optional

`club_required=false`.

Charge without club succeeds.

## CR002 — Club required

Model settings `club_required=true`.

Operation with no resolver/explicit club throws `ClubRequired`.

## CR003 — Custom club resolver

Bind test resolver returning 50.

Operation without explicit club stores club_id=50.

## CR004 — Explicit club beats resolver

Resolver returns 50.

Options specify 60.

Stored club = 60.

---

# 9. Spendable credit — club rule

## RC001 — Credit restricted to Club 1 works in Club 1

Grant restricted credit 1,000,000 to Club 1.

Spend 300,000 in Club 1.

Success.

## RC002 — Same credit blocked in Club 2

Spend same restricted credit in Club 2.

If no other eligible credit:

- `InsufficientBalance` / no eligible balance.
- credit remains unchanged.

## RC003 — Unrestricted credit usable in all clubs

Normal charge has no club scope.

Payment in another club succeeds.

---

# 10. Spendable credit — service rule

## RS001 — Allowed service

Credit allowed service 10.

Payment segment scopes service 10.

Success.

## RS002 — Disallowed service

Credit allowed service 10.

Payment segment service 20.

Credit not eligible.

## RS003 — No service rule

Credit with no service scopes.

Any service segment eligible subject to other rules.

---

# 11. Mixed order segments

## SG001 — Segment sum validation

Payment amount = 700,000.

Segments sum = 600,000.

Throw `InvalidSpendSegments`.

No financial changes.

## SG002 — Unique segment keys

Duplicate segment key in one payment.

Throw `InvalidSpendSegments`.

## SG003 — Restricted credit funds only eligible item

Credits:

```text
Org Credit 300,000 — service 10 only
Personal Credit 500,000 — unrestricted
```

Order:

```text
item A / service 10 / 300,000
item B / service 20 / 400,000
```

Expected:

```text
item A funded by org credit 300,000
item B funded by personal credit 400,000
```

Allocations preserve:

```text
segment_key order-item:A
segment_key order-item:B
```

## SG004 — FIFO within each eligible segment

Multiple eligible credits must still follow FIFO.

---

# 12. Start/end date rules

## D001 — Future credit unavailable

Credit `starts_at` tomorrow.

Today spendable balance excludes it.

Payment cannot use it.

## D002 — Active credit available

`starts_at <= now < expires_at`.

Eligible.

## D003 — Expired credit unavailable

`expires_at <= now`.

Spendable balance excludes it before expiration job runs.

Payment cannot use it.

This prevents spending expired credit even if expiration command has not processed bookkeeping yet.

---

# 13. Withdrawable rule

## WD001 — Normal cashable credit

Charge 500,000 with default cash_withdrawable=true.

Withdrawable = 500,000.

## WD002 — Non-cashable grant

Grant 1,000,000 with `cash_withdrawable=false`.

Total balance includes it.

Withdrawable balance does not.

## WD003 — Deduct cannot consume non-cashable credit

Only non-cashable 1,000,000 exists.

Deduct 100,000 fails.

## WD004 — Deduct uses only cashable portion

Credits:

```text
non-cashable = 1,000,000 first
cashable = 500,000 second
```

Deduct 300,000.

Assert only cashable credit consumed.

---

# 14. Transfer

## T001 — Transfer one credit

Source charge 500,000.

Transfer 200,000.

Expected source:

```text
balance 300,000
source credit remaining 300,000
```

Destination:

```text
balance 200,000
one destination credit
parent_credit_id = source credit id
source_wallet_id = source wallet id
```

## T002 — Transfer multiple source credits

Source:

```text
Credit A 300,000
Credit B 400,000
```

Transfer 500,000.

Destination must receive two credits:

```text
300,000 child of A
200,000 child of B
```

Do not flatten into one credit.

## T003 — Transfer atomicity

Force destination creation error.

Assert source was not debited.

## T004 — Transfer to same wallet

Reject.

## T005 — Transfer idempotency

Retry same transfer key.

Only one source debit and destination credit effect.

---

# 15. Grant

## G001 — Simple organization grant

Organization wallet funded 1,000,000.

Grant 400,000 to employee.

Assert:

- org balance decreases 400,000
- employee increases 400,000
- employee credit parent lineage preserved.

## G002 — Grant restricted club

Rules Club 1.

Destination credit has club scope 1.

## G003 — Grant restricted services

Rules services 10,20.

Scope rows exactly 10 and 20.

## G004 — Combined rules snapshot

Rules:

```text
Club 1
Services 10,20
expires in 30 days
return
cash_withdrawable=false
```

Assert exact snapshot stored.

## G005 — Contract changes later do not alter old credit

After grant, change source Contract/business rules in host fixture.

Assert existing wallet credit scopes/rules unchanged.

## G006 — Grant from multiple source clubs preserves lineage

Organization wallet credits:

```text
600,000 source Club 1
400,000 source Club 2
```

Grant 1,000,000.

Employee receives two child credits retaining respective parent/source club lineage.

---

# 16. Refund

## RF001 — Full refund one credit

Charge 1,000,000.

Pay 400,000.

Refund payment fully.

Expected:

```text
wallet balance back to 1,000,000
credit remaining back to 1,000,000
```

Original consume allocation remains.

New restore allocation exists and references original allocation.

## RF002 — Refund restricted organization credit

Payment used organization non-cashable restricted credit.

Refund.

Assert restored credit remains:

- same credit id
- same club scopes
- same service scopes
- cash_withdrawable=false.

## RF003 — Multi-credit full refund

Payment allocations:

```text
Credit A 500,000
Credit B 100,000
```

Full refund restores both exact amounts.

## RF004 — Partial refund deterministic order

Original allocations:

```text
A = 500,000
B = 100,000
```

Refund 200,000.

Expected:

```text
restore A 200,000
restore B 0
```

## RF005 — Partial refund crossing allocations

Refund 550,000.

Expected:

```text
restore A 500,000
restore B 50,000
```

## RF006 — Prevent over-refund

Payment = 600,000.

Prior refunds total 500,000.

Attempt refund 200,000.

Throw `InvalidRefund`.

No financial change.

## RF007 — Refund idempotency

Retry same refund key.

Only one refund effect.

---

# 17. Segment/item refund

## SR001 — Refund one item

Payment has:

```text
segment A = 300,000
segment B = 400,000
```

Refund segment A only.

Assert only allocations with segment A are restored.

## SR002 — Segment refund cannot consume allocations from another segment

Request amount exceeds refundable value of selected segment.

Throw `InvalidRefund`.

---

# 18. Expiration burn

## EB001 — Burn remaining credit

Credit original 1,000,000.

300,000 already spent.

Remaining 700,000.

Expire action burn.

Run expiration.

Expected:

- debit system transaction 700,000
- consume allocation 700,000
- credit remaining 0
- wallet balance decreases 700,000.

## EB002 — Burn command repeated

Run twice.

Second run creates no additional financial effect.

---

# 19. Expiration return

## ER001 — Return unused grant

Organization grants employee 1,000,000 with return action.

Employee spends 300,000.

Expire.

Expected:

```text
employee remaining grant = 0
700,000 returned toward source wallet/lineage
```

Both sides have proper transactions.

## ER002 — Return preserves source lineage

Source org grant came from Club 1.

After return, restored source-side value still traces to Club 1.

## ER003 — Missing source is not silently burned

Credit configured `return` but source cannot be resolved.

Assert:

- domain failure/loggable error
- no silent balance destruction.

---

# 20. Negative balance

## N001 — Negative default forbidden

No balance.

Payment 100,000.

Fails.

## N002 — Owner override allows negative

Set `allow_negative=true`.

Payment 100,000 with no credits.

Expected:

- transaction may create balance -100,000.
- no fake positive WalletCredit.
- no consume allocation for uncovered amount.

## N003 — Restricted credit does not become cashable due to negative policy

Even if owner allows negative, Deduct must not convert non-cashable grant into cash.

---

# 21. Idempotency

## I001 — Same key same payload

Same operation twice.

One financial effect.

## I002 — Same key different amount

Throw `IdempotencyConflict`.

## I003 — Same key different destination

Transfer/Grant with changed destination throws conflict.

## I004 — Same key different rules

Grant same key but changed rules throws conflict.

## I005 — Null key allowed when not required

Operation succeeds.

## I006 — Required key missing

Effective setting requires idempotency.

Missing key fails before financial write.

---

# 22. Concurrency / double spend

These tests should use a DB capable of real row locking; SQLite-only tests are insufficient for this group.

## CC001 — Two simultaneous payments

Wallet credit = 1,000,000.

Concurrent:

```text
Payment A = 700,000
Payment B = 700,000
```

Expected:

- exactly one succeeds if negative disabled.
- final balance = 300,000.
- remaining credit = 300,000.
- no negative remaining credit.

## CC002 — Payment + Deduct concurrent

Cashable credit 500,000.

Concurrent payment 400,000 and deduct 400,000.

Assert no double spend.

## CC003 — Transfer + payment concurrent

Assert total source consumption cannot exceed available credit.

## CC004 — Grant + payment concurrent

Assert source credit locking prevents over-allocation.

---

# 23. Immutability

## IM001 — WalletTransaction update forbidden

Create transaction.

Attempt model update amount/description.

Throw `ImmutableWalletTransaction`.

## IM002 — WalletTransaction delete forbidden

Delete attempt throws.

## IM003 — WalletAllocation update forbidden

Throw `ImmutableWalletAllocation`.

## IM004 — WalletAllocation delete forbidden

Throw.

## IM005 — Credit remaining amount can be updated only by package service path

Direct public API should not expose arbitrary remaining edits.

---

# 24. Delete safety

## DS001 — Deleting/soft deleting owner does not delete transaction history

Owner lifecycle operation occurs.

Wallet transaction rows remain.

## DS002 — Wallet deletion does not cascade financial history

No cascade delete exists.

## DS003 — Causer deletion does not delete transactions

No `ON DELETE CASCADE` to host users.

---

# 25. Reports

## RP001 — Statement default sort

Assert:

```text
created_at DESC
id DESC
```

## RP002 — Filter by date

Only matching rows returned.

## RP003 — Filter by club

Only selected club transactions.

## RP004 — Filter by types

Only selected transaction types.

## RP005 — Filter by transactionable

Morph filters work.

## RP006 — Pagination max

Requested per_page above configured max is capped.

## RP007 — Summary totals

Given known transactions, assert exact:

- charges
- payments
- refunds
- deducts
- transfers in/out
- current balance.

---

# 26. Facade

## F001 — `Wallet::for($user)->balance()`

Matches Trait balance.

## F002 — Facade charge

Creates same records as Trait.

## F003 — Select wallet via `using`

`using($wallet)` operates on the selected wallet instance.

## F004 — Trait delegates to manager

Mock/spy suitable service and ensure no duplicate financial implementation lives in Trait.

---

# 27. Configurable models

## CM001 — Custom Wallet model

Set config to host subclass.

Auto-created wallet instance is host subclass.

## CM002 — Custom Transaction model

Created transaction uses configured class.

## CM003 — Custom Credit/Allocation models

Records use configured classes.

## CM004 — No hardcoded App namespace

Package test application should work without `App\Models\User`, Club, Contract or Organization classes.

---

# 28. Resolver overrides

## R001 — Custom causer resolver

Resolver returns 900.

Transaction causer_id = 900.

## R002 — Explicit causer beats resolver

Option 901.

Stored 901.

## R003 — Background operation allows null causer

When permitted, expiration works with null causer.

---

# 29. Events

## EV001 — Charge event after commit

Listener queries DB inside event handling and sees committed rows.

## EV002 — Failed operation emits no financial event

Rollback operation.

No event.

## EV003 — Payment event includes allocations/source club

Assert event consumers can determine:

- payment club
- each source credit club
- allocated amount
- segment key.

## EV004 — Transfer event contains both sides

Source/destination transactions and lineage available.

---

# 30. Reconciliation

## RC001 — Healthy wallet passes

Transaction/credit/allocation states align.

No mismatch.

## RC002 — Tampered remaining amount detected

Direct DB tamper credit remaining.

Command reports expected vs actual.

## RC003 — Reconciliation does not auto-fix

After command, tampered DB value remains unchanged.

---

# 31. Legacy migration scenarios

## M001 — Backfill transaction club

Legacy:

```text
Wallet Club 10
Transaction without club
```

After backfill:

```text
Transaction club_id=10
```

## M002 — Multiple legacy wallets merge balance

Legacy user:

```text
Wallet A Club 1 balance 500,000
Wallet B Club 2 balance 300,000
```

Target:

```text
one canonical wallet balance 800,000
opening credit Club 1 500,000
opening credit Club 2 300,000
```

## M003 — Canonical wallet when multiple legacy rows exist

Prefer lowest wallet id when more than one legacy row remains for the same owner.

## M004 — Deterministic canonical choice

Selection must be deterministic across migration runs.

## M005 — Historical transaction count preserved

Merge must not lose rows.

## M006 — Historical transaction amounts unchanged

Exact values preserved.

## M007 — External wallet references audited before repoint

Migration tooling must expose/report external references; no blind destructive delete.

## M008 — Negative legacy balance

Do not generate fake positive opening credit.

Migration must flag/handle explicitly while preserving total balance.

## M009 — Reconciliation after migration

For every owner:

```text
combined old balance == new canonical balance
```

---

# 32. Accounting integration scenarios

The package does not test accounting journal rules, but must prove it exposes sufficient data.

## A001 — Same-club payment source

Charge Club 1, pay Club 1.

Event exposes source Club 1 and operation Club 1.

## A002 — Cross-club payment

Charge Club 1, pay Club 2.

Event exposes:

```text
operation club = 2
source club = 1
amount
```

## A003 — Multi-source cross-club payment

Credits:

```text
Club 1 = 500,000
Club 2 = 300,000
```

Pay 600,000 in Club 3.

Event exposes:

```text
500,000 source Club 1
100,000 source Club 2
operation Club 3
```

This is the minimum data required by the host Accounting Integration.

---

# 33. Performance tests

## PF001 — Balance query count

Balance should not instantiate all transaction rows.

## PF002 — Statement paginated

No unbounded collection for normal report API.

## PF003 — Payment no N+1 scopes

Measure query count with multiple credits/scopes.

## PF004 — Expiration chunking

Large expired set processed in configured chunks.

---

# 34. Acceptance gate

Before release all conditions must be true:

```text
[ ] All test scenarios above automated.
[ ] All tests green.
[ ] No financial cascade delete.
[ ] No direct host model dependencies.
[ ] Trait basic usage works with default config.
[ ] Facade advanced usage works.
[ ] Config/model/operation precedence verified.
[ ] FIFO verified.
[ ] Club rules verified.
[ ] Service rules + segments verified.
[ ] Non-cashable credit verified.
[ ] Transfer lineage verified.
[ ] Grant snapshot verified.
[ ] Partial/full/segment refunds verified.
[ ] Expiration burn/return verified.
[ ] Idempotency verified.
[ ] Real-DB concurrency tests verified.
[ ] Reconciliation verified.
[ ] Production migration reconciliation verified.
[ ] Event payload sufficient for cross-branch accounting.
```

No package release is considered production-ready until this checklist passes.
