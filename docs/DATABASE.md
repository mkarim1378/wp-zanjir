# طرح دیتابیس (Database Schema)
## افزونه‌ی «زنجیر» (Zanjir)

نسخه: 1.0
همه‌ی جداول با پیشوند `{$wpdb->prefix}zanjir_`.
موتور: InnoDB · Charset: utf8mb4.

---

## قرارداد عمومی
- مبالغ پولی به‌صورت `BIGINT` در **کمترین واحد پولی** (ریال صحیح) ذخیره می‌شوند؛ بدون اعشار.
- نرخ‌ها/درصدها به‌صورت **بازدهی صحیح در مبنای ۱۰۰۰۰** (basis-ten-thousand) ذخیره می‌شوند تا اعشار حذف شود (مثلاً ۱۲٫۵٪ = `1250`). جزئیات در `FINANCIAL.md`.
- زمان‌ها `DATETIME` (UTC).
- کلیدهای خارجی منطقی‌اند (اپلیکیشن‌سطح)، با ایندکس مناسب.

---

## ۱. zanjir_affiliates
پروفایل افیلیت/پرسنل.

```sql
CREATE TABLE {prefix}zanjir_affiliates (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  type            ENUM('affiliate','staff') NOT NULL DEFAULT 'affiliate',
  status          ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  national_id_hash CHAR(64) NOT NULL,        -- هش کد ملی (یکتا)
  national_id_enc  VARBINARY(255) NULL,      -- مقدار رمزنگاری‌شده (اختیاری)
  recruit_enabled TINYINT(1) NOT NULL DEFAULT 0,  -- مجوز جذب زیرمجموعه
  annual_sales    BIGINT UNSIGNED NOT NULL DEFAULT 0, -- فروش سالیانه‌ی جاری (برای سقف)
  approved_at     DATETIME NULL,
  created_at      DATETIME NOT NULL,
  updated_at      DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user (user_id),
  UNIQUE KEY uq_national (national_id_hash),
  KEY idx_status (status),
  KEY idx_type (type)
);
```

---

## ۲. zanjir_tree
زنجیره‌ی ثبت‌نام دائمی (رابطه‌ی والد-فرزند).

```sql
CREATE TABLE {prefix}zanjir_tree (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,      -- گره
  parent_id      BIGINT UNSIGNED NULL,          -- بالاسری مستقیم
  staff_id       BIGINT UNSIGNED NULL,          -- پرسنل جذب‌کننده (override)
  depth          INT UNSIGNED NOT NULL DEFAULT 0, -- عمق از ریشه
  path           VARCHAR(255) NOT NULL,         -- materialized path مثل "/1/5/12/"
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_affiliate (affiliate_id),
  KEY idx_parent (parent_id),
  KEY idx_staff (staff_id),
  KEY idx_path (path)
);
```

> `path` (materialized path) پیمایش زنجیره‌ی بالاسری و تشخیص حلقه را ساده می‌کند.

---

## ۳. zanjir_referral_codes
کد معرف هر افیلیت.

```sql
CREATE TABLE {prefix}zanjir_referral_codes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id  BIGINT UNSIGNED NOT NULL,
  code          VARCHAR(64) NOT NULL,
  discount_enabled TINYINT(1) NOT NULL DEFAULT 0,
  discount_rate INT UNSIGNED NOT NULL DEFAULT 0, -- basis-10000
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  KEY idx_affiliate (affiliate_id)
);
```

---

## ۴. zanjir_order_snapshots
اسنپ‌شات تغییرناپذیر هنگام ثبت سفارش.

```sql
CREATE TABLE {prefix}zanjir_order_snapshots (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  referral_code  VARCHAR(64) NULL,
  seller_affiliate_id BIGINT UNSIGNED NULL,   -- فروشنده‌ی مستقیم
  base_amount    BIGINT UNSIGNED NOT NULL,    -- مبنا (پس از تخفیف، بدون مالیات/ارسال)
  tree_cap_rate  INT UNSIGNED NOT NULL,       -- سقف درخت لحظه‌ی ثبت (basis-10000)
  staff_rate     INT UNSIGNED NOT NULL,       -- override پرسنل
  matrix_json    LONGTEXT NOT NULL,           -- ماتریس فعال منجمد
  chain_json     LONGTEXT NOT NULL,           -- زنجیره‌ی بالاسری منجمد
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order (order_id),
  KEY idx_seller (seller_affiliate_id)
);
```

---

## ۵. zanjir_commissions
ردیف پورسانت هر ذی‌نفع در هر سفارش.

```sql
CREATE TABLE {prefix}zanjir_commissions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  snapshot_id    BIGINT UNSIGNED NOT NULL,
  beneficiary_id BIGINT UNSIGNED NOT NULL,    -- افیلیت/پرسنل دریافت‌کننده
  kind           ENUM('tree','staff_override','bonus') NOT NULL,
  tier_level     INT UNSIGNED NULL,           -- جایگاه در ماتریس (برای kind=tree)
  rate           INT UNSIGNED NOT NULL,       -- نرخ اعمال‌شده (basis-10000)
  amount         BIGINT UNSIGNED NOT NULL,    -- مبلغ پورسانت (ریال صحیح)
  status         ENUM('pending','payable','paid','void') NOT NULL DEFAULT 'pending',
  return_window_ends_at DATETIME NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order (order_id),
  KEY idx_beneficiary (beneficiary_id),
  KEY idx_status (status),
  KEY idx_window (return_window_ends_at)
);
```

---

## ۶. zanjir_wallet_ledger
دفتر تراکنش دوطرفه (منبع حقیقت موجودی).

```sql
CREATE TABLE {prefix}zanjir_wallet_ledger (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,
  entry_type     ENUM('credit','debit') NOT NULL,
  bucket         ENUM('pending','payable','withdrawable') NOT NULL,
  amount         BIGINT UNSIGNED NOT NULL,
  balance_after  BIGINT NOT NULL,             -- مانده‌ی همان bucket پس از تراکنش
  ref_type       VARCHAR(32) NOT NULL,        -- commission|settlement|withdrawal|adjustment
  ref_id         BIGINT UNSIGNED NULL,
  note           VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_affiliate_bucket (affiliate_id, bucket),
  KEY idx_ref (ref_type, ref_id)
);
```

> موجودی هر bucket با تجمیع ledger محاسبه می‌شود؛ `balance_after` برای بازرسی سریع.

---

## ۷. zanjir_withdrawals
درخواست‌های برداشت.

```sql
CREATE TABLE {prefix}zanjir_withdrawals (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,
  amount         BIGINT UNSIGNED NOT NULL,
  status         ENUM('requested','approved','rejected','paid') NOT NULL DEFAULT 'requested',
  iban           VARCHAR(34) NULL,
  admin_note     VARCHAR(255) NULL,
  requested_at   DATETIME NOT NULL,
  processed_at   DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_affiliate (affiliate_id),
  KEY idx_status (status)
);
```

---

## ۸. zanjir_settlements
بسته‌های تسویه‌ی ماهانه.

```sql
CREATE TABLE {prefix}zanjir_settlements (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_start   DATE NOT NULL,
  period_end     DATE NOT NULL,
  total_amount   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status         ENUM('draft','reviewed','approved') NOT NULL DEFAULT 'draft',
  approved_by    BIGINT UNSIGNED NULL,
  approved_at    DATETIME NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_status (status)
);
```

---

## ۹. zanjir_bonus_plans
پلن‌های استخر پاداش.

```sql
CREATE TABLE {prefix}zanjir_bonus_plans (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title          VARCHAR(128) NOT NULL,
  metric         ENUM('sales_volume','order_count') NOT NULL,
  threshold      BIGINT UNSIGNED NOT NULL,
  reward_type    ENUM('fixed','rate') NOT NULL,
  reward_value   BIGINT UNSIGNED NOT NULL,    -- مبلغ ثابت یا basis-10000
  period_type    ENUM('monthly','quarterly','yearly','custom') NOT NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_active (active)
);
```

---

## ۱۰. zanjir_fraud_logs
لاگ رویدادهای مشکوک.

```sql
CREATE TABLE {prefix}zanjir_fraud_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type     VARCHAR(48) NOT NULL,        -- self_buy|own_chain|dup_ip|dup_address|ref_loop|dup_national
  severity       ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  order_id       BIGINT UNSIGNED NULL,
  affiliate_id   BIGINT UNSIGNED NULL,
  ip_hash        CHAR(64) NULL,
  meta_json      LONGTEXT NULL,
  reviewed       TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_event (event_type),
  KEY idx_reviewed (reviewed),
  KEY idx_affiliate (affiliate_id)
);
```

---

## ۱۱. تنظیمات
تنظیمات سراسری در `wp_options` با کلید `zanjir_settings` (آرایه‌ی سریالایز/JSON) نگهداری می‌شوند؛ نیازی به جدول اختصاصی ندارد.

---

## نسخه‌بندی و مهاجرت
- کلید `zanjir_db_version` در options.
- در فعال‌سازی/به‌روزرسانی، مقایسه‌ی نسخه و اجرای مهاجرت‌های افزایشی از طریق `dbDelta`.
