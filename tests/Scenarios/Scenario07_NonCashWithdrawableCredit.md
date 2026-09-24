# سناریو ۰۷ — اعتبار سازمانی غیرقابل‌برداشت نقدی

## هدف

تفکیک Payment معمولی از عملیات نقدی (`deduct`) که فقط Creditهای `cash_withdrawable=true` را می‌پذیرد.

## وضعیت اولیه

| Credit | مقدار | cash_withdrawable |
|---|---:|---|
| Personal | 300,000 | true |
| Organization (Grant) | 700,000 | false |

| Wallet | Balance | Withdrawable |
|---|---:|---:|
| Ali | 1,000,000 | 300,000 |

## عملیات ۱ — Service Payment 600,000

### عملیات

```text
pay(600,000)
```

هر دو Credit برای Payment معمولی واجد شرایط‌اند (محدودیت cash فقط روی Deduct اعمال می‌شود).

### انتظار

| Credit | قبل | مصرف | بعد |
|---|---:|---:|---:|
| Personal | 300,000 | 300,000 | 0 |
| Organization | 700,000 | 300,000 | 400,000 |

| Wallet | Balance | Withdrawable |
|---|---:|---:|
| Ali | 400,000 | 0 |

## عملیات ۲ — Deduct 400,000 (شکست)

### عملیات

```text
deduct(400,000)
```

فقط Credit با `cash_withdrawable=true` واجد شرایط است؛ موجودی نقدی قابل‌برداشت = 0.

### انتظار

InsufficientBalance. پس از failure:

| Credit | remaining | cash_withdrawable |
|---|---:|---|
| Personal | 0 | true |
| Organization | 400,000 | false |

هیچ Allocation/Transaction جدیدی برای Deduct ایجاد نشده است.

## کنترل حسابداری سناریو

```text
1,000,000 − 600,000 = 400,000
```

عملیات شکست‌خورده تغییری ایجاد نکرده است.
