← [← Restrictions](09-restrictions.md) | [Documentation Index](../README.md#documentation) | [Next: Idempotency →](11-idempotency.md)

# Organizational Credit

Organizations (companies, contracts, clubs-as-owners) fund members by **granting** from the organization’s wallet into the member’s wallet. Value moves; rules on the member credit are a **snapshot** supplied at grant time.

Aligned with Scenarios **06**, **07**, and **13**.

## Mental model

```text
Organization wallet  --grantTo-->  Member wallet
   (funded by charge)                 (new credit + lineage)
```

```text
Personal Credit      = 300,000   (member charge)
Organization Credit  = 700,000   (grant)
Total                = 1,000,000
```

```php
$member->charge(300_000);
$organization->charge(700_000);

$organization->grantTo($member, 700_000, [
    'allowed_club_ids' => [1],
    'allowed_service_ids' => [10],
    'cash_withdrawable' => false,
    'expires_at' => now()->addMonths(6),
]);
```

After the grant:

- Organization balance decreases by 700,000
- Member balance increases by 700,000
- Member has a credit with `parent_credit_id` / `source_wallet_id` pointing at the org side
- Global sum of both wallets is unchanged by the grant itself

## How org credit can be limited

Pass rules on `grantTo` (see [Grants](08-grants.md) and [Restrictions](09-restrictions.md)):

| Rule | Effect on member |
|------|------------------|
| Club scopes | Spend only in allowed clubs |
| Service scopes | Spend only for allowed services |
| Expiration | Unusable after `expires_at` |
| `cash_withdrawable => false` | Usable for `pay`, not for `deduct` |

Scenario 07 covers non-cash-withdrawable organizational (or charged) credit: payments succeed; cash deduct fails when only non-cashable remaining exists.

## Spending mixed personal + org

FIFO still applies across **all** eligible credits. Personal unrestricted credits and org-restricted credits interleave by creation order and eligibility.

```php
// Scenario 06 style
$member->pay(800_000); // consumes across personal + org credits as eligible
```

## Refunds return to the correct source credits

If a payment consumed both personal and organization credits, refunds restore those same credit rows (allocation order). The organization wallet is **not** automatically credited again by a member refund—restoration is on the member’s consumed credits (which may still carry org lineage fields).

## Lineage

| Field | On member’s granted credit |
|-------|----------------------------|
| `parent_credit_id` | Org credit that was consumed |
| `source_wallet_id` | Organization wallet id |

Useful for reporting and accounting listeners (`WalletCreditGranted`).

## Practical checklist

1. Charge (or otherwise fund) the organization wallet
2. `grantTo` the member with the intended rule snapshot
3. Member `pay` / `deduct` under the right club/service context
4. Refunds restore member credits; re-fund the org only via a new transfer/grant if your business requires it

Next: [Idempotency](11-idempotency.md)

← [← Restrictions](09-restrictions.md) | [Documentation Index](../README.md#documentation) | [Next: Idempotency →](11-idempotency.md)
