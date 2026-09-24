# سناریو ۰۹ — Transfer سپس Refund روی مقصد

## هدف

پس از انتقال اعتبار، پرداخت و بازپرداخت روی کیف‌پول مقصد، lineage Credit منتقل‌شده حفظ شود.

## وضعیت اولیه

| شخص | Credit | مقدار |
|---|---|---:|
| Ali | A | 1,000,000 |
| Reza | — | 0 |

## عملیات ۱ — Transfer

```text
Ali → Reza = 600,000
```

| شخص | Balance |
|---|---:|
| Ali | 400,000 |
| Reza | 600,000 |

Credit رضا: `parent_credit_id = A` ، `source_wallet = Ali`

## عملیات ۲ — Payment رضا

```text
Payment = 500,000
```

| شخص | Balance | Credit منتقل‌شده remaining |
|---|---:|---:|
| Ali | 400,000 | — |
| Reza | 100,000 | 100,000 |

## عملیات ۳ — Refund 200,000

```text
Refund = 200,000
```

| شخص | Balance | Credit منتقل‌شده remaining | Lineage |
|---|---:|---:|---|
| Ali | 400,000 | — | — |
| Reza | 300,000 | 300,000 | همان parent به A |

Refund Credit unrestricted جدید نمی‌سازد.

## وضعیت نهایی

| قلم | مقدار |
|---|---:|
| Ali | 400,000 |
| Reza | 300,000 |
| Total | 700,000 |
| Net spent (۵۰۰k − ۲۰۰k) | 300,000 |

## کنترل حسابداری سناریو

```text
1,000,000 − 300,000 = 700,000
```
