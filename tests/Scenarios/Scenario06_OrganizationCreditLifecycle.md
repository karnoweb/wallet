# سناریو ۰۶ — چرخه اعتبار سازمانی

## هدف

چرخه واقعی ترکیب اعتبار شخصی و سازمانی (Grant) و صحت lineage و restore پس از Refund.

## استراتژی مصرف

FIFO بر اساس زمان ایجاد Credit:

1. Credit Personal (Charge روی Ali)
2. Credit Organization (Grant از Company A)

پس Payment اول ابتدا Personal را مصرف می‌کند.

## وضعیت اولیه

| Credit | صاحب | مقدار | Lineage |
|---|---|---:|---|
| Personal | Ali | 300,000 | ندارد (Charge مستقیم) |
| Organization | Ali | 700,000 | parent = credit سازمان، source_wallet = Company A |

| Wallet | Balance |
|---|---:|
| Ali | 1,000,000 |
| Company A (پس از Grant) | 0 |

## عملیات ۱ — خرید 800,000

### عملیات

```text
Payment #1 = 800,000
```

### انتظار — Allocation

| Credit | مصرف |
|---|---:|
| Personal | 300,000 |
| Organization | 500,000 |

| Credit | قبل | مصرف | بعد |
|---|---:|---:|---:|
| Personal | 300,000 | 300,000 | 0 |
| Organization | 700,000 | 500,000 | 200,000 |

| Wallet | Balance |
|---|---:|
| Ali | 200,000 |

| Payment #1 refundable | 800,000 |

## عملیات ۲ — Partial Refund 300,000

### عملیات

```text
Refund = 300,000
```

طبق ترتیب allocation id، ابتدا allocation شخصی کاملاً restore می‌شود.

### انتظار

| Credit | قبل | بازگشت | بعد | Lineage |
|---|---:|---:|---:|---|
| Personal | 0 | 300,000 | 300,000 | بدون تغییر |
| Organization | 200,000 | 0 | 200,000 | parent حفظ شده |

| Wallet | Balance |
|---|---:|
| Ali | 500,000 |

| تعداد Credit | 2 (بدون credit unrestricted جدید) |
| Payment #1 refundable | 500,000 |

## عملیات ۳ — Payment 400,000

### عملیات

```text
Payment #2 = 400,000
```

### انتظار

| Credit | قبل | مصرف | بعد |
|---|---:|---:|---:|
| Personal | 300,000 | 300,000 | 0 |
| Organization | 200,000 | 100,000 | 100,000 |

| Wallet | Balance |
|---|---:|
| Ali | 100,000 |

## وضعیت نهایی

اعتبار سازمانی هویت و lineage خود را در تمام مراحل حفظ کرده است.

## کنترل حسابداری سناریو

```text
Charge/Grant به Ali: 1,000,000
− Payment #1 800,000
+ Refund 300,000
− Payment #2 400,000
= 100,000
```
