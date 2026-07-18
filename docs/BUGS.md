# Bug Report — Zanjir Plugin v1.2.0

Generated: 2026-07-18

---

## بحرانی (Critical) — Fatal Error / Data Loss

### C1. uninstall.php: نام جدول اشتباه — درخت حذف نمی‌شود
- **فایل:** `uninstall.php:13`
- **مشکل:** نام جدول `affiliate_tree` ذکر شده ولی جدول واقعی `tree` است. هنگام حذف افزونه، جدول درخت ارجاع باقی می‌ماند.
- **.fix:** `affiliate_tree` → `tree`

### C2. settings ذخیره شده از مسیر درست خوانده نمی‌شود
- **فایل:** `admin/class-zanjir-admin.php:41` vs `includes/class-zanjir-settings.php:15`
- **مشکل:** صفحه تنظیمات ادمین در `wp_options` با کلید `zanjir_settings` ذخیره می‌کند. ولی `Zanjir_Matrix` از کلید `zanjir_matrix` می‌خواند. تغییرات ماتریس در صفحه تنظیمات هیچ‌وقت اعمال نمی‌شود.
- **.fix:** یکی از دو مسیر را اصلاح کنید. پیشنهاد: `Zanjir_Matrix::option_key()` را به `zanjir_settings` تغییر دهید و ماتریس را به‌عنوان بخشی از تنظیمات ذخیره کنید، یا فیلدهای ماتریس را جداگانه ثبت کنید.

### C3. خودارجاعی (Self-Referral) — افیلیت از کد خودش استفاده می‌کند
- **فایل:** `includes/class-zanjir-referral-code.php:138-142`
- **مشکل:** `get_tracked_affiliate()` برای کاربران لاگین‌شده، ردیف افیلیت خود کاربر را برمی‌گرداند. یعنی اگر افیلیت A با لینک ارجاع خودش خرید کند، پورسانت به خودش تعلق می‌گیرد.
- **fix:** در `attach_to_order()` اضافه کنید: اگر `seller_id === current_affiliate_id` باشد، رد کنید.

---

## مهم (High)

### H1. `set_status()` تعداد format array با data array همخوانی ندارد
- **فایل:** `includes/class-zanjir-registration.php:237-243`
- **مشکل:** format array همیشه ۳ آیتم دارد (`'%s', '%s', '%s'`)، ولی وقتی `approved_at` اضافه نمی‌شود (غیر از وضعیت approved)، فقط ۲ فیلد به‌روز می‌شود. این عدم تطابق ممکن است در برخی نسخه‌های PHP/MySQL باعث خطا شود.
- **fix:** format array را داینامیک کنید.

### H2. nonce در handle_registration ضعیف است
- **فایل:** `includes/class-zanjir-registration.php:37`
- **مشکل:** `wp_verify_nonce( $_POST['zanjir_register'], 'zanjir_register' )` — nonce value و action یکی هستند. الگوی صحیح: nonce value یک مقدار تصادفی و action یک نام ثابت باشد.
- **fix:** فرم باید `wp_nonce_field( 'zanjir_register_action', 'zanjir_nonce' )` داشته باشد و بررسی `wp_verify_nonce( $_POST['zanjir_nonce'], 'zanjir_register_action' )` انجام شود.

### H3. set_cookie ممکن است دیر اجرا شود
- **فایل:** `includes/class-zanjir-referral-code.php:130`
- **مشکل:** `setcookie()` باید قبل از هر خروجی HTML اجرا شود. هوک `template_redirect` ممکن است بعد از شروع خروجی باشد (بستگی به قالب دارد). اگر `headers already sent` رخ دهد، کوکی ست نمی‌شود و tracking از بین می‌رود.
- **fix:** از `init` یا `set_logged_in_cookie` استفاده کنید، یا خروجی را با `ob_start()` بافر کنید.

### H4. tests/bootstrap.php ناقص است
- **فایل:** `tests/bootstrap.php`
- **مشکل:** فقط ۲ فایل require شده. کلاس‌های `Zanjir_Settings`, `Zanjir_Roles`, `Zanjir_National_Id_Validator`, `Zanjir_Tree_Service`, `Zanjir_Matrix`, `Zanjir_Commission_Engine` و بقیه لود نشده‌اند. هیچ تستی نمی‌تواند اجرا شود.
- **fix:** تمام فایل‌های include از `zanjir.php` را اینجا هم اضافه کنید.

---

## متوسط (Medium)

### M1. Matrix validation وابسته به tree_cap فعلی است
- **فایل:** `includes/commission/class-zanjir-matrix.php:80`
- **مشکل:** `validate()` مقدار `tree_cap` را از تنظیمات جاری می‌خواند. اگر بعد از ذخیره ماتریس، tree_cap تغییر کند، ماتریس ذخیره‌شده ناهمخوان می‌شود. ماتریس باید tree_cap خودش را داشته باشد.
- **fix:** هر ردیف ماتریس باید فیلد `tree_cap` داشته باشد یا مقدار کل در خود ماتریس ذخیره شود.

### M2. محاسبه floor_divide احتمال overflow دارد
- **فایل:** `includes/commission/class-zanjir-commission-engine.php:163`
- **مشکل:** `$base * $rate` اگر base خیلی بزرگ باشد (مثلاً سفارش ۱ میلیارد تومانی = ۱۰ میلیارد ریال × ۲۰۰۰) نتیجه ۲۰ تریلیون می‌شود که از محدوده ۳۲ بیتی PHP خارج است.
- **fix:** از `(int) floor( (float) $base * $rate / 10000 )` استفاده کنید اا `$base` را به float تبدیل کنید قبل از ضرب.

### M3. `link_parent` آپدیت بیهوده انجام می‌دهد
- **فایل:** `includes/class-zanjir-registration.php:143-149`
- **مشکل:** خط ۱۴۳-۱۴۹ فقط `updated_at` را آپدیت می‌کند بدون هیچ تغییر مفیدی. این query اضافی است.
- **fix:** آپدیت بیهوده را حذف کنید.

### M4. cookie security: SameSite تنظیم نشده
- **فایل:** `includes/class-zanjir-referral-code.php:130`
- **مشکل:** پارامتر `SameSite` در setcookie تنظیم نشده. در مرورگرهای مدرن ممکن است کوکی در context‌های third-party کار نکند.
- **fix:** پارامتر SameSite=Lax را اضافه کنید.

---

## پایین (Low)

### L1. debug.log در ریپوزیتوری نباید باشد
- **فایل:** `debug.log`
- **مشکل:** فایل لاگ حساس اطلاعات سرور را در خود دارد و نباید در git باشد.
- **fix:** به `.gitignore` اضافه کنید و از ریپوزیتوری حذف کنید.

### L2. `.mimocode` در gitignore با الگو `.*/` هندل می‌شود
- **وضعیت:** درست کار می‌کند. نیاز به اصلاح ندارد.

---

## خلاصه اولویت‌بندی

| اولویت | شماره | شرح |
|---|---|---|
| بحرانی | C1 | uninstall table name |
| بحرانی | C2 | settings key mismatch |
| بحرانی | C3 | self-referral bug |
| مهم | H1 | format array mismatch |
| مهم | H2 | weak nonce |
| مهم | H3 | cookie timing |
| مهم | H4 | test bootstrap |
| متوسط | M1 | matrix tree_cap |
| متوسط | M2 | integer overflow |
| متوسط | M3 | unnecessary query |
| متوسط | M4 | SameSite cookie |
| پایین | L1 | debug.log in repo |
