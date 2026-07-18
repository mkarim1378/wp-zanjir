# مدل ماشین حالت‌ها (State Machines)
## افزونه‌ی «زنجیر» (Zanjir)

نسخه: 1.0

---

## ۱. افیلیت (Affiliate)

```
register
──────────────────▶ pending
                       │
            admin approve │ │ admin reject
                       ▼ ▼
                   approved rejected
                       │
            admin suspend │ │ admin reactivate
                       ▼ ▲
                   suspended ──┘
```

| از | رویداد | به | نگهبان (Guard) |
|---|---|---|---|
| — | ثبت‌نام | pending | کد ملی معتبر و یکتا |
| pending | تأیید ادمین | approved | بررسی هویت (اختیاری هوک) |
| pending | رد ادمین | rejected | — |
| approved | تعلیق | suspended | تصمیم ادمین |
| suspended | فعال‌سازی مجدد | approved | — |

نکته: فقط افیلیت approved می‌تواند کد معرف فعال داشته باشد و پورسانت بگیرد.

## ۲. مجوز جذب زیرمجموعه (Recruit Permission)

```
recruit_enabled = 0 ──(annual_sales ≥ annual_cap)──▶ recruit_enabled = 1
       ▲                                                     │
       └──────────(دوره جدید / بازنشانی سالیانه)◀────────────┘
```

Guard: `annual_sales ≥ annual_cap` (سقف قابل‌تنظیم).
بازنشانی سالیانه: تصمیم باز (سال شمسی از تاریخ عضویت یا تقویمی).

## ۳. پورسانت (Commission)

```
create (on completed)
─────────────────────▶ pending
                          │
     refund within window │ │ window passed & no refund
                          ▼ ▼
                      void payable
                          │
                   settlement approved
                          ▼
                        paid
```

| از | رویداد | به | Guard |
|---|---|---|---|
| — | تکمیل سفارش | pending | اسنپ‌شات موجود، پیش‌بررسی ضدتقلب پاس |
| pending | عودت داخل پنجره | void | now < return_window_ends_at |
| pending | پایان پنجره بدون عودت | payable | now ≥ return_window_ends_at و بدون refund |
| payable | تأیید تسویه | paid | بسته‌ی تسویه approved |

گذارها یک‌طرفه‌اند؛ void و paid حالت‌های پایانی‌اند.
هر گذار یک رکورد ledger متناظر تولید می‌کند (بخش کیف پول).

## ۴. کیف پول — سطل‌ها (Wallet Buckets)

پورسانت در طول عمرش بین سطل‌های موجودی جابه‌جا می‌شود:

```
[pending bucket] ──(payable)──▶ [payable bucket] ──(settle)──▶ [withdrawable bucket]
        │
        └──(void)──▶ حذف از pending (بدون انتقال)
```

هر جابه‌جایی = دو رکورد ledger (debit از سطل مبدأ، credit به سطل مقصد).

## ۵. درخواست برداشت (Withdrawal)

```
affiliate request
────────────────────▶ requested
                          │
            admin approve │ │ admin reject
                          ▼ ▼
              approved rejected ──▶ (بازگشت مبلغ به withdrawable)
                          │
                mark as paid │
                          ▼
                        paid
```

| از | رویداد | به | Guard |
|---|---|---|---|
| — | ثبت درخواست | requested | amount ≤ withdrawable balance |
| requested | تأیید ادمین | approved | بررسی موجودی مجدد + قفل مبلغ |
| requested | رد ادمین | rejected | آزادسازی مبلغ قفل‌شده |
| approved | واریز | paid | ثبت debit نهایی از withdrawable |

## ۶. بسته‌ی تسویه (Settlement Batch)

```
prepare_batch
──────────────▶ draft ──(حسابداری بازبینی)──▶ reviewed ──(تأیید)──▶ approved
```

در گذار به approved: همه‌ی پورسانت‌های payable دوره ⟶ paid و به سطل withdrawable منتقل می‌شوند.
حالت approved پایانی است.

## ۷. ثبت‌نام هویتی با استعلام (اختیاری)

```
submit ──▶ pending ──(hook: zanjir_verify_identity)──┐
                                                      │
                                                      ▼
                                          ┌──── verified ────▶ (به‌سمت approved ادمین)
                                          └──── failed ──────▶ flagged (لاگ ضدتقلب)
```

اگر درگاه استعلام غیرفعال باشد، مستقیم منتظر تأیید دستی ادمین می‌ماند.

## ۸. ناوردا‌های سراسری (Global Invariants)

- هیچ پورسانتی بدون اسنپ‌شات ایجاد نمی‌شود.
- هیچ گذاری به payable پیش از پایان پنجره‌ی عودت مجاز نیست.
- مجموع سطل‌های موجودی هر افیلیت همیشه با تجمیع ledger سازگار است.
- حالت‌های پایانی (void, paid, rejected, approved-settlement) بازگشت‌ناپذیرند.
