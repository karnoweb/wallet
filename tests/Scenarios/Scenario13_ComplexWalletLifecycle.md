# سناریو ۱۳ — چرخه واقعی پیچیده (مهم‌ترین سناریو)

## هدف

یک داستان مالی چندنفره با Charge، Grant، Payment، Refund و Transfer را از ابتدا تا انتها با تراز جهانی کنترل می‌کند.

## وضعیت اولیه

| شخص / منبع | Credit | مقدار |
|---|---|---:|
| Ali | Personal | 500,000 |
| Ali | Organization (Grant از Company A) | 1,000,000 |
| Reza | Personal | 200,000 |

| جمع علی | 1,500,000 |
| جمع رضا | 200,000 |
| **Global Total** | **1,700,000** |

FIFO برای علی: ابتدا Personal، سپس Organization.

## مرحله ۱ — خرید علی 600,000

### عملیات

```text
Payment #1 = 600,000
```

### Allocation

| Credit | مصرف |
|---|---:|
| Personal | 500,000 |
| Organization | 100,000 |

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 900,000 | |
| Reza | 200,000 | |
| جمع | | 1,100,000 |

## مرحله ۲ — Partial Refund 200,000

### عملیات

```text
Refund #1 = 200,000  (روی Payment #1)
```

Restore به ترتیب allocation: ابتدا Personal.

| Credit علی | remaining پس از Refund |
|---|---:|
| Personal | 200,000 |
| Organization | 900,000 |

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 1,100,000 | |
| Reza | 200,000 | |
| جمع | | 1,300,000 |

Net spent تا اینجا: ۴۰۰,۰۰۰

## مرحله ۳ — Transfer علی → رضا 300,000

### عملیات

```text
Transfer = 300,000
```

مصرف از علی (FIFO): Personal ۲۰۰k + Organization ۱۰۰k.

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 800,000 | |
| Reza | 500,000 | |
| جمع | | **1,300,000** (بدون تغییر) |

Creditهای منتقل‌شده در رضا `parent_credit_id` و `source_wallet_id` معتبر دارند.

## مرحله ۴ — خرید رضا 350,000

```text
Payment #2 = 350,000
```

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 800,000 | |
| Reza | 150,000 | |
| جمع | | 950,000 |

## مرحله ۵ — خرید علی 250,000

```text
Payment #3 = 250,000
```

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 550,000 | |
| Reza | 150,000 | |
| جمع | | 700,000 |

## مرحله ۶ — Refund خرید رضا 100,000

```text
Refund #2 = 100,000  (روی Payment #2)
```

| شخص | Balance | Global |
|---|---:|---:|
| Ali | 550,000 | |
| Reza | 250,000 | |
| جمع | | **800,000** |

## وضعیت نهایی

| شخص | Balance |
|---|---:|
| Ali | 550,000 |
| Reza | 250,000 |
| **Final Global** | **800,000** |

## کنترل حسابداری سناریو (تراز مستقل)

```text
Initial Money     1,700,000
− Payment #1       −600,000
+ Refund #1        +200,000
± Transfer              0
− Payment #2       −350,000
− Payment #3       −250,000
+ Refund #2        +100,000
────────────────────────────
Final Money          800,000
```

این تساوی در تست assert می‌شود.
