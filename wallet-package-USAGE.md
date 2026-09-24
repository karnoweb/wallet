# Laravel Wallet & Credit Package — Usage Guide

This document is for developers consuming `karnoweb/laravel-wallet`.

The package is intentionally designed so that simple usage stays simple, while advanced credit rules, organizations, contracts, refunds and inter-branch accounting remain available when needed.

---

# 1. Core concepts

You normally work with only two concepts:

- **Wallet**: the owner’s wallet.
- **Wallet Transaction**: charge, payment, transfer, deduction or refund.

Internally the package also tracks:

- **Credit**: where available money came from and what rules it has.
- **Allocation**: which credit funded a payment.

You do not need to manipulate Credit or Allocation directly in normal application code.

---

# 2. Installation

```bash
composer require karnoweb/laravel-wallet
```

Publish configuration:

```bash
php artisan vendor:publish --tag=wallet-config
```

Publish migrations if your project manages package migrations manually:

```bash
php artisan vendor:publish --tag=wallet-migrations
```

Run migrations:

```bash
php artisan migrate
```

---

# 3. Simple setup with one Trait

Add the Trait to any Eloquent model that should own a wallet:

```php
use Illuminate\Database\Eloquent\Model;
use Karnoweb\Wallet\Concerns\HasWallets;

class User extends Model
{
    use HasWallets;
}
```

That model now has wallet relationships, balance methods, transaction methods and reports.

The same Trait may be used on:

```text
User
Locker
Organization
Contract
```

or any other Eloquent model.

The package does not need to know what type of business entity the model represents.

---

# 4. Wallet

Each owner has exactly one wallet. There is no `wallets()` multi-wallet relation.

With default `auto_create=true`, the wallet is created in the Trait `created` hook as soon as the owner is persisted (for example `User::create(...)`). You do not need a host `User::created` listener for this.

Resolve the wallet:

```php
$wallet = $user->wallet();
```

Read without creating (returns the existing row, or `null` when `auto_create` was off and none exists):

```php
$wallet = $user->wallet(create: false);
```

`wallet(true)` remains a race-safe safety net for owners that predate the Trait or temporarily had `auto_create=false`.

You can change `auto_create` in configuration or on the model via `walletSettings()`.

---

# 5. Balance

Total wallet balance:

```php
$balance = $user->balance();
```

This is the user’s total monetary balance.

For restricted organizational/contract credits, total balance alone does not answer whether all money can be used for a specific purchase.

---

# 6. Spendable balance

Example:

```php
$amount = $user->spendableBalance([
    'club_id' => 1,
    'scopes' => [
        'service' => [10],
    ],
]);
```

This answers:

> How much of this wallet can be spent for this context?

A user may have:

```text
Total balance       = 2,000,000
Spendable balance   = 1,200,000
```

because some credits may belong to another club, another service, or may have expired.

---

# 7. Withdrawable balance

```php
$amount = $user->withdrawableBalance();
```

This returns only credit that may be converted to cash through Deduct/withdrawal.

Example:

```text
Personal cashable credit        500,000
Organization non-cashable       1,000,000

Total balance                   1,500,000
Withdrawable balance              500,000
```

---

# 8. Charge

Simple charge:

```php
$transaction = $user->charge(1_000_000);
```

With branch/club:

```php
$transaction = $user->charge(1_000_000, [
    'club_id' => 1,
]);
```

With audit references:

```php
$transaction = $user->charge(1_000_000, [
    'club_id' => 1,
    'causer_id' => auth()->id(),
    'transaction_id' => $paymentTransaction->id,
    'transactionable' => $payment,
    'description' => 'POS payment',
    'idempotency_key' => 'payment:'.$payment->id,
]);
```

A normal charge creates unrestricted credit by default.

---

# 9. Pay from wallet

Simple payment:

```php
$payment = $user->pay(250_000);
```

Typical order payment:

```php
$payment = $user->pay(250_000, [
    'club_id' => $order->club_id,
    'transactionable' => $order,
    'idempotency_key' => 'order-payment:'.$order->id,
]);
```

The package automatically selects eligible credits using FIFO.

FIFO means the oldest eligible credit is consumed first.

---

# 10. Mixed-service order

If organizational credit is restricted to certain services, do not send a mixed order as one anonymous amount.

Pass spend segments.

Example:

```php
$payment = $user->pay(700_000, [
    'club_id' => 1,
    'transactionable' => $order,

    'segments' => [
        [
            'key' => 'order-item:101',
            'amount' => 300_000,
            'scopes' => [
                'service' => [10],
            ],
        ],
        [
            'key' => 'order-item:102',
            'amount' => 400_000,
            'scopes' => [
                'service' => [20],
            ],
        ],
    ],
]);
```

The sum of segment amounts must equal the payment amount.

`segment_key` is preserved internally in allocations so that later item-level refunds are exact.

For simple purchases, segments are optional.

---

# 11. Cash withdrawal / Deduct

```php
$transaction = $user->deduct(200_000, [
    'club_id' => 1,
    'transactionable' => $refundRequest,
    'idempotency_key' => 'cash-refund:'.$refundRequest->id,
]);
```

Only credits with:

```text
cash_withdrawable = true
```

are eligible.

Organizational credit can therefore be spendable but not cashable.

---

# 12. Transfer between wallets

Using the Trait:

```php
$result = $user->transferTo(
    $otherUser,
    300_000,
    [
        'club_id' => 1,
        'idempotency_key' => 'transfer:123',
    ]
);
```

Destination may be:

- another model using `HasWallets`
- a specific Wallet model

Transfer is atomic.

If source money came from multiple credits, the destination receives multiple credits so the original source lineage is not lost.

---

# 13. Organization or Contract grant

Example: Organization gives an employee 1,000,000 credit.

```php
$result = $organization->grantTo(
    $employee,
    1_000_000
);
```

This is unrestricted organizational credit unless rules are passed.

---

# 14. Grant restricted to one club

```php
$result = $organization->grantTo(
    $employee,
    1_000_000,
    [
        'allowed_club_ids' => [1],
    ],
    [
        'idempotency_key' => 'contract-grant:500:employee:20',
    ]
);
```

The employee still has one global wallet.

The restriction belongs to the granted Credit, not the Wallet.

---

# 15. Grant restricted to services

```php
$result = $organization->grantTo(
    $employee,
    1_000_000,
    [
        'allowed_service_ids' => [10, 11, 12],
    ]
);
```

This credit can only fund payment segments matching those services.

---

# 16. Grant with expiration

```php
$result = $organization->grantTo(
    $employee,
    1_000_000,
    [
        'expires_at' => now()->addMonth(),
        'expire_action' => 'return',
    ]
);
```

Possible expire actions:

```text
none
return
burn
```

`return` returns remaining credit toward its source lineage/wallet.

`burn` removes remaining credit through a real wallet transaction.

---

# 17. Non-cashable organization credit

```php
$result = $organization->grantTo(
    $employee,
    1_000_000,
    [
        'cash_withdrawable' => false,
    ]
);
```

The employee can spend the credit if other rules allow it.

The employee cannot cash it out through `deduct()`.

---

# 18. Combining rules

```php
$result = $organization->grantTo(
    $employee,
    1_000_000,
    [
        'allowed_club_ids' => [1],
        'allowed_service_ids' => [10, 20],
        'starts_at' => now(),
        'expires_at' => now()->addMonths(3),
        'expire_action' => 'return',
        'cash_withdrawable' => false,
    ]
);
```

Rules are snapshotted when the Grant is created.

Changing the organization/contract later does not silently change existing granted credits.

---

# 19. Refund

Full refund:

```php
$refund = $user->refund($paymentTransaction);
```

Partial refund:

```php
$refund = $user->refund(
    $paymentTransaction,
    200_000
);
```

The refund restores the same credits used by the original payment.

Example:

```text
Original payment:
400,000 personal credit
200,000 organization credit

Full refund:
400,000 restored to personal credit
200,000 restored to the same organization credit
```

The organization credit remains restricted and non-cashable if it originally was.

---

# 20. Refund one order item / segment

For applications using payment segments:

```php
$refund = $user->refund(
    $paymentTransaction,
    300_000,
    [
        'segment_keys' => [
            'order-item:101',
        ],
        'idempotency_key' => 'order-item-refund:101',
    ]
);
```

Only allocations belonging to that segment may be restored.

---

# 21. Using the Facade

Import:

```php
use Karnoweb\Wallet\Facades\Wallet;
```

Balance:

```php
Wallet::for($user)->balance();
```

Charge:

```php
Wallet::for($user)->charge(1_000_000, [
    'club_id' => 1,
]);
```

Payment:

```php
Wallet::for($user)->pay(200_000, [
    'club_id' => 2,
    'transactionable' => $order,
]);
```

Specific wallet instance:

```php
Wallet::for($user)
    ->using($wallet)
    ->pay(200_000, [
        'club_id' => 2,
    ]);
```

The Trait methods are thin wrappers over this same manager.

---

# 22. Model-level settings override

Global config:

```php
// config/wallet.php
'defaults' => [
    'auto_create' => true,
    'allow_negative' => false,
    'club_required' => false,
    'idempotency_required' => false,
],
```

Per owner class:

```php
'owners' => [
    \App\Models\Contract::class => [
        'allow_negative' => true,
    ],
],
```

Per model instance/class behavior:

```php
class Contract extends Model
{
    use HasWallets;

    public function walletSettings(): array
    {
        return [
            'allow_negative' => true,
            'club_required' => true,
        ];
    }
}
```

Operation-specific override has the highest priority when the specific setting is permitted as an operation option.

Resolution order:

```text
operation
model
config owner class
config defaults
```

---

# 23. Custom package models

Extend the package model:

```php
namespace App\Models;

class Wallet extends \Karnoweb\Wallet\Models\Wallet
{
    protected $appends = [
        // ...
    ];
}
```

Configure:

```php
'models' => [
    'wallet' => \App\Models\Wallet::class,
],
```

The same approach is available for transaction, credit, allocation and operation models.

Do not replace models with unrelated classes; custom models must extend the package base models.

---

# 24. Club resolver

The package does not call application-specific helpers.

Default behavior only accepts explicit `club_id`.

A host application may create:

```php
class CurrentClubResolver implements ClubResolver
{
    public function resolve(?Model $owner = null, array $options = []): ?int
    {
        return $options['club_id']
            ?? app(CurrentClub::class)->id();
    }
}
```

Then configure:

```php
'resolvers' => [
    'club' => \App\Wallet\CurrentClubResolver::class,
],
```

This keeps the package independent from your application.

---

# 25. Causer resolver

By default:

```text
explicit causer_id
→ authenticated user id
→ null
```

Background jobs such as expiration may therefore operate without a real user unless your application requires a system causer.

A custom resolver can enforce your policy.

---

# 26. Reports from the Trait

Statement:

```php
$rows = $user->statement([
    'from' => now()->subMonth(),
    'to' => now(),
    'club_id' => 1,
    'types' => ['charge', 'payment'],
    'per_page' => 50,
]);
```

Transaction query:

```php
$query = $user->transactions();

$payments = $query
    ->where('type', 'payment')
    ->latest('id')
    ->get();
```

Summary:

```php
$summary = $user->summary([
    'from' => now()->startOfMonth(),
    'to' => now(),
]);
```

Example fields:

```text
balance
charges
payments
deducts
refunds
transfers_in
transfers_out
```

---

# 27. Accounting integration

The Wallet package does not create accounting documents.

Listen to package events in the application:

```text
WalletCharged
WalletPaid
WalletTransferred
WalletDeducted
WalletRefunded
WalletCreditGranted
WalletCreditExpired
```

For a payment event, allocations expose the source credit and therefore the source club.

Example:

```text
User credit:
500,000 from Club 1
300,000 from Club 2

Payment:
600,000 in Club 3

Allocations:
500,000 ← Club 1
100,000 ← Club 2
```

The application’s Accounting Integration can then create inter-branch entries.

This accounting logic must stay outside the Wallet package.

---

# 28. Idempotency

For requests that may retry, always pass an idempotency key:

```php
$user->pay(500_000, [
    'idempotency_key' => 'order:'.$order->id.':wallet-payment',
]);
```

If the same request is sent again with the same key and same financial payload, the package returns the prior operation result instead of creating duplicate financial effects.

Reusing the same key with a different amount/rules/destination throws an `IdempotencyConflict`.

For production financial HTTP flows, enable:

```php
'idempotency_required' => true,
```

through config/model settings where appropriate.

---

# 29. Expiring credits

Run manually:

```bash
php artisan wallet:expire-credits
```

Schedule in Laravel:

```php
$schedule->command('wallet:expire-credits')->hourly();
```

The command is idempotent.

It only processes credits whose:

```text
expires_at <= now
remaining_amount > 0
```

Expiration is recorded through wallet transactions; the package does not silently edit balance history.

---

# 30. Reconciliation

Check all wallets:

```bash
php artisan wallet:reconcile
```

One wallet:

```bash
php artisan wallet:reconcile 100
```

Use this after migration or when investigating financial inconsistencies.

The command compares transaction balance, credit remaining values and allocation-derived credit state.

It reports differences but does not auto-fix by default.

---

# 31. Production migration from club-specific wallets

Current legacy model:

```text
User
├── Wallet Club 1
├── Wallet Club 2
└── Wallet Club 3
```

Target:

```text
User
└── Global Wallet
    ├── Credit sourced from Club 1
    ├── Credit sourced from Club 2
    └── Credit sourced from Club 3
```

Recommended process:

1. Add new columns/tables without deleting old columns.
2. Backfill `wallet_transactions.club_id` from legacy `wallets.club_id`.
3. Create Opening Credits from current wallet balances, not by replaying a full year of history.
4. Select a canonical wallet per owner.
5. Audit every external `wallet_id` reference before repointing.
6. Move new writes to global wallet.
7. Reconcile balances.
8. Keep legacy `club_id` for a stabilization period.
9. Remove it in a later release.

Never deploy this as a single destructive migration.

---

# 32. Recommended application boundary

Application `PaymentService` remains the orchestrator.

Example responsibility split:

```text
Order / POS / Reservation
        ↓
Application PaymentService
        ↓
Wallet Package
        ↓
Wallet event
        ↓
Application Accounting Integration
        ↓
Accounting Package
```

Wallet package does not know how an Order is settled or which accounting accounts are debit/credit.

---

# 33. Important rules

Keep these rules in mind:

1. Wallet belongs to owner, not branch.
2. Branch belongs to transaction.
3. Credit stores source and restrictions.
4. Allocation stores exactly what was consumed/restored.
5. Refund restores original credit.
6. Grant rules are snapshots.
7. Financial transaction/allocation history is immutable.
8. No cascade delete of financial history.
9. Advanced features do not change the basic Trait API.
10. Accounting remains a separate integration.
