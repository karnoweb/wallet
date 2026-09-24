# سناریو ۱۱ — چرخه کامل Idempotency

## هدف

Retry با همان کلید نباید اثر مالی تکراری بسازد؛ فقط همان Transaction منطقی قبلی را برمی‌گرداند.

## وضعیت اولیه

| Wallet | Balance |
|---|---:|
| Ali | 1,000,000 |

## عملیات ۱

```text
Payment = 300,000
idempotency_key = payment-001
```

| Balance | تعداد Payment |
|---:|---:|
| 700,000 | 1 |

## عملیات ۲ — Retry همان درخواست

```text
Payment = 300,000
idempotency_key = payment-001
```

| Balance | تعداد Payment | نتیجه |
|---:|---:|---|
| 700,000 | 1 | همان Transaction عملیات ۱ |

نه Balance = 400,000.

## عملیات ۳

```text
Payment = 200,000
idempotency_key = payment-002
```

| Balance | تعداد Payment |
|---:|---:|
| 500,000 | 2 |

## عملیات ۴ — Retry payment-002

| Balance | تعداد Payment |
|---:|---:|
| 500,000 | 2 |

## وضعیت نهایی

| قلم | مقدار |
|---|---:|
| Net payment | 500,000 |
| Final balance | 500,000 |
| Duplicate financial effect | ندارد |

## کنترل حسابداری سناریو

```text
1,000,000 − 500,000 = 500,000
```
