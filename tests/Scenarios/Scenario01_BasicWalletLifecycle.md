# سناریو ۰۱ — چرخه پایه کیف‌پول

## هدف

ساده‌ترین چرخه کامل Wallet را از شارژ تا چند پرداخت و بازپرداخت بررسی می‌کند:

```text
Charge → Payment → Partial Refund → Payment → Full Remaining Refund
```

## وضعیت اولیه

| موجودیت | مقدار |
|---|---:|
| Ali — Balance | 0 |
| Credit | ندارد |

## عملیات ۱ — Charge

### عملیات

```text
Charge = 1,000,000
```

### انتظار

| Credit | original | remaining |
|---|---:|---:|
| A | 1,000,000 | 1,000,000 |

| Wallet | Balance |
|---|---:|
| Ali | 1,000,000 |

## عملیات ۲ — Payment

### عملیات

```text
Payment #1 = 300,000
```

### انتظار

مصرف از Credit A:

| Credit | قبل | مصرف | بعد |
|---|---:|---:|---:|
| A | 1,000,000 | 300,000 | 700,000 |

| Wallet | Balance |
|---|---:|
| Ali | 700,000 |

| Allocation | مقدار |
|---|---:|
| Credit A → Payment #1 | 300,000 |

| Payment #1 refundable | 300,000 |

## عملیات ۳ — Partial Refund

### عملیات

از Payment #1:

```text
Refund = 100,000
```

### انتظار

بازگشت به همان Credit A (نه credit جدید unrestricted):

| Credit | قبل | بازگشت | بعد |
|---|---:|---:|---:|
| A | 700,000 | 100,000 | 800,000 |

| Wallet | Balance |
|---|---:|
| Ali | 800,000 |

| Payment #1 refundable | 200,000 |

## عملیات ۴ — Payment جدید

### عملیات

```text
Payment #2 = 500,000
```

### انتظار

| Credit | قبل | مصرف | بعد |
|---|---:|---:|---:|
| A | 800,000 | 500,000 | 300,000 |

| Wallet | Balance |
|---|---:|
| Ali | 300,000 |

## عملیات ۵ — Refund باقی‌مانده Payment اول

### عملیات

```text
Refund = 200,000   (بقیه Payment #1)
```

### انتظار

| Credit | قبل | بازگشت | بعد |
|---|---:|---:|---:|
| A | 300,000 | 200,000 | 500,000 |

| Wallet | Balance |
|---|---:|
| Ali | 500,000 |

| Payment #1 refundable | 0 |

## وضعیت نهایی

| قلم | مقدار |
|---|---:|
| Total charged | 1,000,000 |
| Payment #1 | 300,000 |
| Refund روی Payment #1 | 300,000 (۱۰۰k + ۲۰۰k) |
| Payment #2 | 500,000 |
| Net spent | 500,000 |
| Final balance | 500,000 |

## کنترل حسابداری سناریو

```text
1,000,000 − 500,000 = 500,000
```

نتیجه نهایی با Balance کیف‌پول یکی است.
