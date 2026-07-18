# سند معماری (Architecture)
## افزونه‌ی «زنجیر» (Zanjir)

نسخه: 1.0
پیشوند فنی: `zanjir_`

---

## ۱. نمای کلی

«زنجیر» یک افزونه‌ی وردپرس با معماری لایه‌ای و رویدادمحور است که
روی هوک‌های ووکامرس سوار می‌شود. هسته‌ی مالی از رابط کاربری جدا است،
و محاسبات سنگین به‌صورت **غیرمسدودکننده** (async/cron) اجرا می‌شوند.

### اصول طراحی
- **جداسازی دغدغه‌ها:** موتور کمیسیون، درخت، کیف پول، ضدتقلب هرکدام ماژول مستقل.
- **اسنپ‌شات‌محور:** محاسبات از روی داده‌ی منجمدشده‌ی سفارش، نه تنظیمات جاری.
- **رویدادمحور:** واکنش به هوک‌های ووکامرس؛ عدم دخالت در مسیر بحرانی checkout.
- **تنظیمات به‌عنوان داده:** ماتریس و نرخ‌ها داده‌ی قابل‌ویرایش‌اند، نه کد هاردکد.

---

## ۲. لایه‌بندی (Layers)

```
┌─────────────────────────────────────────────┐
│ Presentation                                │
│ admin/ (پنل ادمین) | public/ (داشبورد)      │
├─────────────────────────────────────────────┤
│ Application / Services                      │
│ Commission · Tree · Wallet · Fraud · Bonus  │
│ Settlement · Registration                   │
├─────────────────────────────────────────────┤
│ Domain                                      │
│ موجودیت‌ها، قواعد، ماشین‌های حالت           │
├─────────────────────────────────────────────┤
│ Infrastructure                              │
│ Repositories · $wpdb · Cron · Hooks · Crypto│
├─────────────────────────────────────────────┤
│ Platform                                    │
│ WordPress · WooCommerce · MySQL              │
└─────────────────────────────────────────────┘
```

## ۳. ماژول‌ها (Bounded Contexts)

### ۳.۱ Registration & Identity
ثبت‌نام افیلیت، اعتبارسنجی کد ملی، یکتایی، فلو تأیید دستی.
سرویس‌ها: Zanjir_Registration_Service, Zanjir_National_Id_Validator.
هوک استعلام شخص‌ثالث: zanjir_verify_identity (فیلتر).

### ۳.۲ Tree & Attribution
ساخت و پیمایش زنجیره‌ی دائمی، تشخیص حلقه، تولید اسنپ‌شات زنجیره.
سرویس: Zanjir_Tree_Service.
خروجی کلیدی: resolve_upline_chain( affiliate_id, max_depth ).

### ۳.۳ Commission Engine
بارگذاری ماتریس فعال، اعمال روی اسنپ‌شات، محاسبه‌ی سهم هر لایه + override پرسنل.
سرویس: Zanjir_Commission_Engine.
محض محاسبه؛ بدون I/O مستقیم به‌جز خواندن اسنپ‌شات.

### ۳.۴ Order Lifecycle
شنونده‌ی رویدادهای ووکامرس؛ تولید اسنپ‌شات هنگام ثبت، گذار وضعیت‌ها، مدیریت پنجره‌ی عودت.
سرویس: Zanjir_Order_Observer, Zanjir_Commission_Lifecycle.

### ۳.۵ Wallet & Ledger
دفتر تراکنش دو‌طرفه، موجودی، درخواست برداشت.
سرویس: Zanjir_Wallet_Service, Zanjir_Ledger.

### ۳.۶ Settlement
گذار payable، بسته‌بندی ماهانه، تأیید حسابداری، واریز به موجودی قابل‌برداشت.
سرویس: Zanjir_Settlement_Service.

### ۳.۷ Anti-Fraud
بررسی خرید از خود/زنجیره، مالتی‌اکانت، حلقه، رصد IP/آدرس، مجوز جذب، لاگ مشکوک.
سرویس: Zanjir_Fraud_Guard (زنجیره‌ای از قواعد قابل‌افزونه).

### ۳.۸ Bonus Pool
تعریف پلن، محاسبه‌ی پاداش حجمی از استخر ۵٪.
سرویس: Zanjir_Bonus_Service.

### ۳.۹ Settings
منبع واحد حقیقت برای تنظیمات؛ کش‌شده در حافظه.
سرویس: Zanjir_Settings.

## ۴. جریان اصلی رویداد (Order → Commission)

```
woocommerce_checkout_order_processed
└─> Order_Observer::capture_snapshot()
    resolve upline chain (Tree)
    load active matrix + rates (Settings)
    store immutable snapshot (_zanjir_order_snapshot)
    run pre-checks (Fraud_Guard: self-buy, own-chain)

woocommerce_order_status_completed
└─> Lifecycle::mark_return_window()
    schedule payable-check @ (completed_at + refund_window)
    create commission rows (status = pending) ← از روی اسنپ‌شات

cron: zanjir_check_return_window
└─> برای هر پورسانت pending که پنجره‌اش گذشته:
    if order refunded within window → void
    else → status = payable ; Ledger credit (pending→payable)

ماهانه: Settlement::prepare_batch()
└─> تجمیع payable ها → بازبینی حسابداری → approve
    → Wallet: انتقال به موجودی قابل‌برداشت
```

نکته‌ی کارایی (NFR-PERF-01): در مرحله‌ی ۱ فقط اسنپ‌شات و پیش‌بررسی سبک انجام می‌شود.
ایجاد ردیف‌های پورسانت در مرحله‌ی ۲ و ترجیحاً به‌صورت زمان‌بندی‌شده تا مسیر checkout سبک بماند.

## ۵. یکپارچگی با ووکامرس (Hooks)

| هوک ووکامرس | مصرف‌کننده | کاربرد |
|---|---|---|
| `woocommerce_checkout_order_processed` | Order_Observer | تولید اسنپ‌شات، پیش‌بررسی |
| `woocommerce_order_status_completed` | Lifecycle | آغاز پنجره‌ی عودت، تولید پورسانت |
| `woocommerce_order_refunded` | Lifecycle | ابطال پورسانت داخل پنجره |
| `woocommerce_cart_calculate_fees` / coupon hooks | Discount | اعمال تخفیف کد معرف + سقف |
| `woocommerce_new_order` | Order_Observer | ثبت مرجع کد معرف |

هوک‌های اختصاصی افزونه (extension points):
- `zanjir_verify_identity` (filter) — اتصال استعلام شخص‌ثالث.
- `zanjir_commission_result` (filter) — بازبینی نتیجه‌ی محاسبه پیش از ذخیره.
- `zanjir_fraud_rules` (filter) — افزودن قاعده‌ی ضدتقلب.
- `zanjir_before_payable` (action) — قلاب پیش از قابل‌تسویه‌شدن.

## ۶. مدل داده (نمای بالا)

جداول اختصاصی با پیشوند `zanjir_`؛ جزئیات در DATABASE.md.

هسته: affiliates, tree, referral_codes, commissions,
order_snapshots, wallet_ledger, withdrawals, bonus_plans,
fraud_logs, settlements.

## ۷. زمان‌بندی (Cron)

- `zanjir_check_return_window` — بررسی پایان پنجره‌ی عودت (روزانه/ساعتی).
- `zanjir_recalc_annual_cap` — به‌روزرسانی وضعیت مجوز جذب.
- `zanjir_bonus_evaluate` — ارزیابی دوره‌ای استخر پاداش.

استفاده از WP-Cron با امکان جایگزینی توسط system cron برای دقت بیشتر.

## ۸. کش و کارایی

- تنظیمات و ماتریس فعال در object cache نگه داشته می‌شوند.
- کوئری‌های داشبورد با ایندکس مناسب و صفحه‌بندی.
- محاسبات مالی بدون واکشی مکرر تنظیمات (از اسنپ‌شات خوانده می‌شود).

## ۹. تصمیمات معماری کلیدی (ADR خلاصه)

- **ADR-01:** جداول اختصاصی به‌جای CPT/meta برای داده‌ی مالی — دلیل: کارایی، یکپارچگی، کوئری‌پذیری.
- **ADR-02:** اسنپ‌شات تغییرناپذیر سفارش — دلیل: تغییرات آتی ساختار نباید گذشته را عوض کند.
- **ADR-03:** ذخیره‌ی مبالغ به‌صورت عدد صحیح (کمترین واحد پولی) — دلیل: پرهیز از خطای اعشار شناور.
- **ADR-04:** override پرسنل به‌عنوان بودجه‌ی مستقل — دلیل: جداسازی از سقف درخت ۲۰٪.
- **ADR-05:** موتور کمیسیون بدون I/O (تابع محض) — دلیل: تست‌پذیری بالا.
