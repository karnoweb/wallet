# سناریو ۱۵ — یکپارچگی Transaction و Allocation

## هدف

جمع Allocationهای یک Payment دقیقاً برابر مبلغ Payment باشد و Refund با Restore به allocationهای اصلی لینک شود؛ هیچ orphan وجود نداشته باشد.

## وضعیت اولیه

| Credit | مقدار |
|---|---:|
| A | 200,000 |
| B | 300,000 |
| C | 500,000 |

| Balance | 1,000,000 |

## عملیات ۱ — Payment 750,000

### Allocation مورد انتظار (FIFO)

| Credit | مصرف |
|---|---:|
| A | 200,000 |
| B | 300,000 |
| C | 250,000 |
| **جمع** | **750,000** |

| Credit | بعد |
|---|---:|
| A | 0 |
| B | 0 |
| C | 250,000 |

| Balance | 250,000 |

هر Allocation:

- به همان Payment لینک است
- به یک Credit موجود اشاره دارد
- نوع `consume` دارد

## عملیات ۲ — Refund 350,000

### انتظار

| Balance پس از Refund | 600,000 |
| Payment refundable باقی‌مانده | 400,000 |

هر Restore Allocation:

- به Refund Transaction لینک است
- `original_allocation_id` یکی از consumeهای Payment است
- به همان Credit اصلی اشاره دارد
- نوع `restore` دارد

## وضعیت نهایی — Integrity

| بررسی | نتیجه |
|---|---|
| SUM(payment allocations) = payment amount | ✓ |
| SUM(refund restores) = 350,000 | ✓ |
| SUM(refunds) ≤ payment | ✓ |
| Allocation بدون Transaction | 0 |
| Transaction بدون operation (جریان پکیج) | 0 |

## کنترل حسابداری سناریو

```text
1,000,000 − 750,000 + 350,000 = 600,000
```
