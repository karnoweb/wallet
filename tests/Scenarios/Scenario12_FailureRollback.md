# سناریو ۱۲ — شکست عملیات نباید State را عوض کند

## هدف

هر failure باید از نظر مالی کاملاً side-effect free باشد.

## وضعیت اولیه

| شخص | Balance |
|---|---:|
| Ali | 500,000 |
| Reza | 0 |

## عملیات ۱ — Payment 700,000 (شکست)

InsufficientBalance. پس از آن:

| Ali Balance |
|---:|
| 500,000 |

## عملیات ۲ — Transfer 800,000 (شکست)

InsufficientBalance. پس از آن:

| Ali | Reza |
|---:|---:|
| 500,000 | 0 |

## عملیات ۳ — Payment معتبر 300,000

| Ali Balance | Payment refundable |
|---:|---:|
| 200,000 | 300,000 |

## عملیات ۴ — Refund 400,000 بیش از Payment (شکست)

InvalidRefund. پس از آن:

| Ali Balance | Payment refundable |
|---:|---:|
| 200,000 | 300,000 |

## وضعیت نهایی

فقط عملیات ۳ اثر مالی داشته است.

## کنترل حسابداری سناریو

```text
500,000 − 300,000 = 200,000
```
