# گزارش باگ‌ها و بهبودهای Zanjir

**نسخه بررسی‌شده:** 2.3.1  
**تاریخ:** ۱۴۰۵/۰۶/۰۷  
**روش:** بازبینی کد منبع، مستندات داخلی، و اجرای `composer test` (۲۳ تست — همه سبز)

این سند برای برنامه‌ریزی فیکس‌هاست: ابتدا مشکلات **عملکردی/مالی**، سپس **قابلیت اطمینان**، و در نهایت **UI/UX**.

---

## خلاصه اولویت‌ها

| اولویت | تعداد | معنی |
|--------|-------|------|
| **P0** | 4 | خطای مالی یا داده — باید قبل از production فیکس شود |
| **P1** | 7 | رفتار غلط یا feature ناقص در مسیر اصلی کاربر |
| **P2** | 8 | لبه، امنیت، یا وابستگی به cron/محیط |
| **P3** | 12 | UX/UI و یکپارچگی تجربه (پس از فیکس P0–P2) |

---

## P0 — بحرانی (مالی / یکپارچگی داده)

### 1. تسویه دوره را نادیده می‌گیرد — همهٔ پورسانت‌های `payable` پرداخت می‌شوند ✅ فیکس شد

**محل:** `includes/wallet/class-zanjir-settlement-service.php` — `prepare_batch()` و `approve()`

**مشکل:** فرم ادمین تاریخ `period_start` / `period_end` می‌گرفت، ولی کوئری فقط `WHERE status = 'payable'` بود. هیچ فیلتری روی تاریخ سفارش، `created_at` پورسانت، یا `return_window_ends_at` اعمال نمی‌شد. نتیجه: با «تأیید بسته»، پورسانت‌های خارج از دوره هم `paid` و به `withdrawable` منتقل می‌شدند.

**راه‌حل اعمال‌شده:**
- جدول `zanjir_settlement_items` (DB v1.1.0) برای قفل پورسانت‌ها در `prepare_batch`.
- فیلتر دوره روی `DATE(updated_at)` هنگام payable شدن.
- `approve()` فقط ردیف‌های همان batch را پردازش می‌کند.
- UI: نمایش «کل payable» vs «payable دوره پیش‌فرض» + ستون تعداد کمیسیون.

---

### 2. بودجهٔ سفارش (>10000) فقط در JS هشدار می‌دهد — سرور اجازهٔ ذخیره می‌دهد ✅ فیکس شد

**محل:** `admin/class-zanjir-admin.php` — `sanitize_settings()`  
**مرتبط:** `assets/js/zanjir-admin-settings.js`

**مشکل:** اگر `tree_cap + staff_rate + bonus_pool > 10000` باشد، UI قرمز می‌شود ولی `sanitize_settings` این را رد نمی‌کرد. مدیر می‌توانست پیکربندی مالی غیرواقعی ذخیره کند (بیش از ۱۰۰٪ بودجه روی هر سفارش).

**راه‌حل اعمال‌شده:**
- validation در `sanitize_settings` با `add_settings_error` و بازگشت به مقادیر قبلی.
- دکمهٔ ذخیره در JS هنگام over-budget غیرفعال می‌شود.

---

### 3. Race condition در درخواست برداشت هم‌زمان ✅ فیکس شد

**محل:** `includes/wallet/class-zanjir-withdrawal-service.php` — `request()` و `available_balance()`

**مشکل:** دو درخواست POST هم‌زمان هر دو `available_balance` یکسان می‌دیدند، هر دو insert می‌شدند، و مجموع مبالغ از `withdrawable` بیشتر می‌شد. هیچ transaction، row lock، یا unique constraint روی «مبلغ رزرو شده» وجود نداشت.

**راه‌حل اعمال‌شده:**
- `START TRANSACTION` + `SELECT ... FOR UPDATE` روی affiliate و درخواست‌های باز.
- بررسی مجدد `sum_open_requests` بعد از insert؛ rollback در صورت overflow.

---

### 4. انتقال ledger بدون transaction اتمی

**محل:** `includes/wallet/class-zanjir-ledger.php` — `transfer()`

**مشکل:** `debit` و `credit` دو insert جدا هستند. اگر credit بعد از debit شکست بخورد، rollback دستی (`transfer_rollback`) best-effort است؛ در crash وسط عملیات مانده ناسازگار می‌ماند. همین الگو در `transition_to_payable`، `approve` تسویه، و `reject` برداشت تکرار می‌شود.

**راه‌حل پیشنهادی:**
- Transaction DB دور هر عملیات مالی چندمرحله‌ای.
- یا job reconciliation شبانه که `balance_after` را از تجمیع entries بازسازی کند.

---

## P1 — بالا (مسیر اصلی کاربر / feature شکسته)

### 5. پورسانت فقط روی وضعیت `completed` ساخته می‌شود

**محل:** `includes/class-zanjir-commission-lifecycle.php` — `woocommerce_order_status_completed`

**مشکل:** فروشگاه‌هایی که بعد از پرداخت سفارش را `processing` نگه می‌دارند (رایج در ایران)، تا تکمیل دستی هیچ پورسانت pending نمی‌گیرند. اسنپ‌شات در checkout ساخته می‌شود ولی commission row خیر.

**راه‌حل پیشنهادی:**
- تنظیم «وضعیت محرک پورسانت» (`processing` | `completed`) در Operations.
- یا hook روی هر دو با idempotency (`has_commissions`).

---

### 6. پس از پایان پنجرهٔ مرجوعی، refund دیگر پورسانت را void نمی‌کند

**محل:** `includes/class-zanjir-refund-handler.php` — `on_order_refunded()`

**مشکل:** فقط وقتی `$now <= $end` (داخل پنجره) void می‌شود. اگر cron قبلاً پورسانت را `payable` کرده باشد و بعداً مشتری مرجوعی کند، handler کاری نمی‌کند — در حالی که QA و مستندات «clawback از payable» را ذکر می‌کنند.

**راه‌حل پیشنهادی:**
- خارج از پنجره هم روی refund کامل (یا سیاست تعریف‌شده) `void_commissions` را صدا بزنید — که همین حالا bucket `payable` را debit می‌کند.
- برای مرجوعی جزئی، سیاست را explicit کنید (void کامل vs نسبی).

---

### 7. `annual_sales` سالانه reset نمی‌شود — سقف «سالانه» در عمل مادام‌العمر است

**محل:** `includes/class-zanjir-recruit-service.php` — `add_sales()`, cron `zanjir_recalc_annual_cap`

**مشکل:** `annual_sales` فقط increment می‌شود؛ cron فقط `recruit_enabled` را refresh می‌کند، نه reset سالانه. افیلیت یک‌بار به سقف برسد، برای همیشه `recruit_enabled = 1` می‌ماند حتی در سال بعد با فروش صفر.

**راه‌حل پیشنهادی:**
- در cron سالانه (یا اول هر سال شمسی/میلادی قابل تنظیم) `annual_sales = 0` و recalc `recruit_enabled`.
- یا محاسبهٔ rolling 12-month از `order_snapshots` به‌جای counter ساده.

---

### 8. موتور bonus دوره را رعایت نمی‌کند

**محل:** `includes/bonus/class-zanjir-bonus-service.php` — `evaluate_active_plans()`

**مشکل:**
- `period_type` در DB هست ولی UI/create فقط `monthly` پیش‌فرض می‌گذارد.
- `order_count` کل lifetime snapshots را می‌شمارد، نه ماه جاری.
- `sales_volume` از `annual_sales` تجمعی استفاده می‌کند.
- پاداش مستقیم به `withdrawable` می‌رود (رد شدن از pending → payable → settlement).

**راه‌حل پیشنهادی:**
- فیلتر تاریخ روی snapshots/commissions بر اساس `period_type`.
- UI برای `period_type` و غیرفعال‌کردن plan.
- هم‌راستا کردن مسیر مالی bonus با policy محصول (pending یا settlement).

---

### 9. تخفیف per-code فقط از DB — بدون UI ادمین

**محل:** `zanjir_referral_codes.discount_enabled`, `discount_rate`  
**مستند:** `docs/USER_GUIDE.md` §۱۱

**مشکل:** تنظیم سراسری «فعال‌سازی تخفیف معرف» روشن است ولی کدهای تولیدشده `discount_rate = 0` دارند. مدیر بدون SQL تخفیف فعال نمی‌کند — feature در عمل غیرقابل استفاده.

**راه‌حل پیشنهادی:**
- در صفحه Affiliates: ستون/مودال «نرخ تخفیف (basis-10000)» + toggle.
- یا نرخ پیش‌فرض سراسری در تنظیمات که روی کدهای جدید اعمال شود.

---

### 10. خطاهای settlement/bonus در admin نمایش داده نمی‌شوند

**محل:** `admin/class-zanjir-admin.php` — `handle_settlement_prepare()`, `handle_bonus_create()`

**مشکل:** `prepare_batch()` و `create_plan()` می‌توانند `WP_Error` برگردانند؛ handler نتیجه را چک نمی‌کند و همیشه redirect با `done=prepared` / `done=created` می‌زند.

**راه‌حل پیشنهادی:**
- چک `is_wp_error( $result )` و redirect با `?error=...` یا `set_transient` + `admin_notices`.

---

### 11. ریدایرکت اشتباه بعد از تأیید افیلیت

**محل:** `includes/class-zanjir-registration.php` — `handle_approve()` خط 209

**مشکل:** بعد از Approve به `admin.php?page=zanjir` (تنظیمات) می‌رود، نه `zanjir-affiliates`. رد افیلیت همین مشکل را دارد.

**راه‌حل پیشنهادی:**
```php
wp_safe_redirect( admin_url( 'admin.php?page=zanjir-affiliates&status=approved' ) );
```

---

## P2 — متوسط (لبه، امنیت، وابستگی cron)

### 12. وابستگی کامل به WP-Cron برای آزادسازی پورسانت

**محل:** `Zanjir_Commission_Lifecycle::schedule_check()`

**مشکل:** اگر cron اجرا نشود، پورسانت‌ها برای همیشه `pending` می‌مانند. fallback روی `admin_init`، Action Scheduler، یا دکمه «اجرای دستی cron پنجره» وجود ندارد.

**راه‌حل:** `transition_to_payable` batch برای ردیف‌هایی که `return_window_ends_at <= NOW()`؛ یا ادغام با Action Scheduler ووکامرس.

---

### 13. `check_return_window` تاریخ پایان را دوباره validate نمی‌کند

**محل:** `includes/class-zanjir-commission-lifecycle.php` — `check_return_window()`

**مشکل:** فقط `has_status('refunded')` چک می‌شود. اگر event زود trigger شود (clock skew، cron دستی)، ممکن است قبل از پایان واقعی پنجره payable شود.

**راه‌حل:** قبل از transition، `return_window_ends_at <= current_time('mysql', true)` روی commissions/challenge.

---

### 14. checkout مهمان — bypass بخشی از fraud

**محل:** `includes/fraud/class-zanjir-fraud-guard.php` — `own_chain` rule

**مشکل:** اگر `buyer_id = 0` (مهمان)، چک زنجیرهٔ خود (`own_chain`) skip می‌شود. self_buy برای مهمان هم skip است (منطقی)، ولی خرید از زنجیرهٔ خود بدون حساب کاربری ممکن است.

**راه‌حل:** تطبیق email/phone billing با affiliate user؛ یا flag سخت‌گیرانه برای guest + referral cookie.

---

### 15. بدون WooCommerce هیچ هشدار admin نیست

**محل:** `includes/class-zanjir.php` — `define_public_hooks()`

**مشکل:** اگر WC غیرفعال باشد، shortcodeها و hookهای checkout ثبت نمی‌شوند ولی منوی Zanjir در admin هست — به نظر «کار می‌کند» ولی هیچ سفارشی پردازش نمی‌شود.

**راه‌حل:** `admin_notices` + `is_plugin_active('woocommerce/woocommerce.php')`.

---

### 16. `force_persian_locale` — i18n افزونه همیشه fa_IR

**محل:** `includes/class-zanjir.php` — `force_persian_locale`, `force_persian_mofile`

**مشکل:** حتی روی سایت انگلیسی، رشته‌های Zanjir فارسی force می‌شوند. برای multisite یا فروش بین‌المللی مشکل‌ساز است.

**راه‌حل:** force فقط وقتی locale سایت `fa_*` است، یا تنظیم «زبان پنل Zanjir».

---

### 17. بارگذاری CSS/JS shortcode با page builder / Gutenberg

**محل:** `public/class-zanjir-public.php` — `enqueue_assets()`

**مشکل:** فقط `has_shortcode( $post->post_content, ... )` — در بلوک shortcode، Elementor، یا shortcode تودرتو asset load نمی‌شود.

**راه‌حل:**
```php
if ( has_shortcode( $content, 'zanjir_dashboard' ) || apply_filters( 'zanjir_force_enqueue', false ) ) ...
```
یا enqueue در خود callback shortcode با flag static.

---

### 18. IBAN بدون validation و اختیاری در فرم

**محل:** `public/class-zanjir-public.php` — فرم برداشت

**مشکل:** فیلد IBAN `required` نیست؛ format IR/chk digit validate نمی‌شود. ادمین ممکن است درخواست بدون شبا ببیند.

**راه‌حل:** `required` + regex/validator ایران (۲۶ char، IR + ۲۴ رقم)؛ ذخیره normalized.

---

### 19. Pagination گزارش‌ها حداکثر ۲۰ صفحه

**محل:** `admin/class-zanjir-admin-reports.php` — `render_pagination()` — `$p <= 20`

**مشکل:** دادهٔ بیشتر از ۱۰۰۰ ردیف (20×50) از UI قابل دسترسی نیست؛ export CSV هست ولی browse ناقص است.

**راه‌حل:** prev/next استاندارد وردپرس یا حذف cap 20.

---

## P3 — UX/UI (بعد از فیکس عملکردی)

### 20. ناهمگونی UI ادمین — فقط Settings مدرن است

**محل:** `admin/class-zanjir-admin.php` vs `render_settings_page()`

**مشکل:** Settlements، Withdrawals، Affiliates، Fraud، Bonus هنوز `widefat` خام وردپرس هستند؛ Settings hero/card دارد. CSS مشترک (`zanjir-admin.css`) برای reports تا حدی هست ولی shell یکپارچه نیست.

**راه‌حل:** component مشترک (header، card، status badge، empty state) و اعمال روی همه submenuها.

---

### 21. پارامتر `done=` بدون admin notice

**محل:** همه redirectهای `admin-post` (settlements، withdrawals، fraud، bonus، affiliates)

**مشکل:** URL `?done=approved` می‌آید ولی کاربر feedback بصری نمی‌بیند.

**راه‌حل:** helper `Zanjir_Admin_Notices::flash( $key, $message )` روی `admin_notices`.

---

### 22. Affiliates: نمایش `user_id` به‌جای نام/ایمیل

**محل:** `render_affiliates_page()`

**راه‌حل:** `get_userdata()` → display_name + link به user edit؛ ستون کد معرف و موجودی خلاصه.

---

### 23. Withdrawals admin: فقط affiliate_id

**راه‌حل:** نام کاربر، لینk به Affiliates، confirm dialog برای Approve/Reject، فیلتر status.

---

### 24. Fraud queue: جزئیات رویداد نمایش داده نمی‌شود

**محل:** `render_fraud_page()` — `meta_json` در DB هست ولی در جدول نیست

**راه‌حل:** expandable row با meta، لینک order، severity badge رنگی.

---

### 25. Bonus plans: بدون حذف/غیرفعال‌سازی در UI

**راه‌حل:** دکمه Deactivate + وضعیت active/inactive در جدول.

---

### 26. داشبورد افیلیت (frontend) بسیار minimal

**محل:** `public/class-zanjir-public.php`, `assets/css/zanjir-public.css`

**کمبودها:**
- بدون کارت موجودی / visual hierarchy
- لینک معرف بدون دکمه «کپی»
- بدون نمایش کد معرف (فقط URL)
- بدون تاریخ در لیست برداشت‌ها
- `max` روی input مبلغ برداشت = available balance نیست
- فرم برداشت به `admin-post.php` می‌رود — برای UX بهتر AJAX یا همان صفحه با پیام

**راه‌حل:** redesign با grid کارت‌ها، copy-to-clipboard JS، progress نزدیک سقف جذب، empty states فارسی.

---

### 27. فرم ثبت‌نام افیلیت UX ضعیف

**کمبودها:** بدون راهنمای فرمت کد ملی، بدون feedback inline validation، دکمه submit generic.

**راه‌حل:** mask ۱۰ رقمی، توضیح «کد معرف اختیاری»، استایل هم‌سطح dashboard.

---

### 28. Settings: tab state فقط با JS — بدون `?tab=` در URL اولیه server-side کافی است ولی bookmark share خوب است (JS دارد ✅)

**بهبود کوچک:** بعد از save موفق، همان tab فعال بماند (`redirect` با `&tab=matrix`).

---

### 29. Reports: فیلترها شلوغ و بدون reset

**راه‌حل:** دکمه «پاک کردن فیلتر»، چیدمان responsive، sticky summary.

---

### 30. Staff assignment: بدون UI برای `staff_id` روی tree

**مستند:** `docs/QA_CHECKLIST.md` — «Set staff_id on tree row (DB or future UI)»

**راه‌حل:** در Affiliates یا Tree report dropdown «پرسنل مسئول» برای هر node.

---

### 31. عدم نمایش واحد «ریال» / «٪» یکسان در dashboard

**راه‌حل:** `number_format_i18n` + پسوند «ریال» در همه balanceها؛ tooltip برای basis-10000 در admin.

---

## موارد بررسی‌شده که باگ **نیستند** (یا by design)

| موضوع | توضیح |
|--------|--------|
| `annual_cap = 0` → همه recruit_enabled | با label «0 = unlimited» در settings، یعنی بدون آستانه — رفتار منطقی است |
| void روی partial refund داخل پنجره | all-or-nothing طبق PRD |
| PHPUnit سبز | تست‌های money/matrix/validator هسته را پوشش می‌دهند؛ integration WC ندارند |
| HPOS compatibility | در `zanjir.php` declare شده |

---

## پیشنهاد ترتیب اجرا (اسپرینت)

### اسپرینت 1 — مالی (P0) ✅
1. ~~Settlement period + settlement_items~~ — **فیکس شد** (v1.1.0)
2. ~~Server-side budget validation~~ — **فیکس شد**
3. ~~Transaction / lock برداشت~~ — **فیکس شد**

### اسپرینت 2 — مسیر اصلی (P1)
4. Commission trigger status  
5. Refund clawback بعد از پنجره  
6. annual_sales reset  
7. UI تخفیف per affiliate  
8. Admin error notices + redirect fix  

### اسپرینت 3 — reliability (P2)
9. Cron fallback  
10. WC missing notice  
11. Shortcode enqueue fix  
12. IBAN validation  

### اسپرینت 4 — UX (P3)
13. Unified admin shell  
14. Dashboard redesign  
15. Affiliates/Fraud/Withdrawals polish  

---

## فایل‌های کلیدی برای شروع فیکس

```
includes/wallet/class-zanjir-settlement-service.php   ← P0 #1
admin/class-zanjir-admin.php                          ← P0 #2, P1 #10
includes/wallet/class-zanjir-withdrawal-service.php   ← P0 #3
includes/wallet/class-zanjir-ledger.php               ← P0 #4
includes/class-zanjir-commission-lifecycle.php        ← P1 #5, P2 #12–13
includes/class-zanjir-refund-handler.php              ← P1 #6
includes/class-zanjir-recruit-service.php             ← P1 #7
includes/bonus/class-zanjir-bonus-service.php         ← P1 #8
public/class-zanjir-public.php                        ← P3 #26–27
assets/css/zanjir-admin.css                           ← P3 #20
```

---

*این سند خروجی بازبینی static است. بعد از هر فیکس، مورد مربوطه را علامت بزنید و تست دستی طبق `docs/QA_CHECKLIST.md` را تکرار کنید.*
