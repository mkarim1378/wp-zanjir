<?php
/**
 * One-off script to generate languages/*.po/*.pot/*.mo — run: php bin/build-languages.php
 *
 * @package Zanjir
 */

$entries = array(
	'Zanjir'                                                      => 'زنجیر',
	'Zanjir Settings'                                             => 'تنظیمات زنجیر',
	'Zanjir Settlements'                                          => 'تسویه‌های زنجیر',
	'Zanjir Withdrawals'                                          => 'برداشت‌های زنجیر',
	'Zanjir Affiliates'                                           => 'افیلیت‌های زنجیر',
	'Zanjir Reports'                                              => 'گزارش‌های زنجیر',
	'Zanjir Affiliate'                                            => 'افیلیت زنجیر',
	'Zanjir Staff'                                                => 'پرسنل زنجیر',
	'Settings'                                                    => 'تنظیمات',
	'Commission'                                                  => 'پورسانت',
	'Commissions'                                                 => 'پورسانت‌ها',
	'Tree Depth'                                                  => 'عمق درخت',
	'Tree Cap (basis-10000)'                                      => 'سقف درخت (مبنای ۱۰۰۰۰)',
	'Staff Override (basis-10000)'                                => 'سهم پرسنل (مبنای ۱۰۰۰۰)',
	'Bonus Pool (basis-10000)'                                    => 'صندوق پاداش (مبنای ۱۰۰۰۰)',
	'Discount & Double-Dip'                                       => 'تخفیف و دابل‌دیپ',
	'Enable Referral Discount'                                    => 'فعال‌سازی تخفیف معرف',
	'Coupon Compatibility'                                        => 'سازگاری با کوپن',
	'Max Total Discount (basis-10000)'                            => 'سقف کل تخفیف (مبنای ۱۰۰۰۰)',
	'Double-Dip (Discount + Commission)'                          => 'دابل‌دیپ (تخفیف + پورسانت)',
	'WARNING: When disabled, orders with referral discount will NOT generate commissions.' => 'هشدار: اگر غیرفعال باشد، سفارش‌های دارای تخفیف معرف پورسانت نمی‌گیرند.',
	'Operations'                                                  => 'عملیات',
	'Refund window (days)'                                        => 'پنجره مرجوعی (روز)',
	'Annual recruit cap (Rial)'                                   => 'سقف جذب سالانه (ریال)',
	'Referral code length'                                        => 'طول کد معرف',
	'Commission matrix'                                           => 'ماتریس پورسانت',
	'Depth × position rates'                                      => 'نرخ‌های عمق × جایگاه',
	'Budget: tree %1$d + staff %2$d + bonus %3$d = %4$d / 10000 (%5$s%%).' => 'بودجه: درخت %1$d + پرسنل %2$d + پاداش %3$d = %4$d / ۱۰۰۰۰ (%5$s٪).',
	'Commission & budget'                                         => 'پورسانت و بودجه',
	'Tree depth and share of each order'                          => 'عمق درخت و سهم هر سفارش',
	'Depth × position rate table'                                 => 'جدول نرخ عمق × جایگاه',
	'Referral discount rules'                                     => 'قوانین تخفیف معرف',
	'Refund window, caps, codes'                                  => 'پنجره مرجوعی، سقف‌ها و کدها',
	'All commission, discount, and operations options in one place. Switch sections without leaving this page — one save covers everything.' => 'همهٔ گزینه‌های پورسانت، تخفیف و عملیات در یک صفحه. بین بخش‌ها جابه‌جا شوید؛ یک ذخیره همه را ثبت می‌کند.',
	'Order budget'                                                => 'بودجه سفارش',
	'Budget breakdown'                                            => 'جزئیات بودجه',
	'Total exceeds 10000 (100%). Reduce shares before saving.'    => 'جمع از ۱۰۰۰۰ (۱۰۰٪) بیشتر است. قبل از ذخیره سهم‌ها را کم کنید.',
	'Settings sections'                                           => 'بخش‌های تنظیمات',
	'Configure commission rates and tree structure. Values use basis-10000 (10000 = 100%).' => 'نرخ پورسانت و ساختار درخت را تنظیم کنید. مقادیر بر مبنای ۱۰۰۰۰ هستند (۱۰۰۰۰ = ۱۰۰٪).',
	'How many upline levels earn from a sale (1–3).'              => 'چند لایه بالادست از فروش سهم می‌برند (۱ تا ۳).',
	'Total share allocated to the referral tree.'                 => 'کل سهم اختصاص‌یافته به درخت معرف.',
	'Fixed share for the assigned staff member.'                  => 'سهم ثابت برای پرسنل تخصیص‌یافته.',
	'Pool used by bonus plans when targets are met.'              => 'صندوق مورد استفاده پلن‌های پاداش هنگام رسیدن به هدف.',
	'Allow affiliates to offer a checkout discount via their referral code.' => 'اجازه دهید افیلیت‌ها با کد معرف در تسویه‌حساب تخفیف بدهند.',
	'Allow WooCommerce coupons together with the referral discount.' => 'اجازه استفاده هم‌زمان کوپن ووکامرس با تخفیف معرف.',
	'Ceiling for referral discount + other discounts when compatibility is on.' => 'سقف مجموع تخفیف معرف و سایر تخفیف‌ها وقتی سازگاری روشن است.',
	'Days before pending commission becomes payable.'             => 'تعداد روز تا تبدیل پورسانت در انتظار به قابل پرداخت.',
	'Yearly recruitment earnings ceiling per affiliate (0 = unlimited).' => 'سقف درآمد جذب سالانه هر افیلیت (۰ = نامحدود).',
	'Characters in newly generated referral codes (4–32).'        => 'تعداد کاراکتر کدهای معرف جدید (۴ تا ۳۲).',
	'Changes apply after you save. Matrix rows are validated against the tree cap.' => 'تغییرات بعد از ذخیره اعمال می‌شوند. ردیف‌های ماتریس با سقف درخت اعتبارسنجی می‌شوند.',
	'Save all settings'                                           => 'ذخیره همه تنظیمات',
	'Row %d'                                                      => 'ردیف %d',
	'Balanced'                                                    => 'متوازن',
	'Sum must equal tree cap'                                     => 'مجموع باید برابر سقف درخت باشد',
	'Configure commission rates and tree structure.'              => 'نرخ پورسانت و ساختار درخت را تنظیم کنید.',
	'Configure referral discount and double-dip behavior.'        => 'تخفیف معرف و رفتار دابل‌دیپ را تنظیم کنید.',
	'Return window, recruitment cap, and referral code length.'   => 'پنجره مرجوعی، سقف جذب و طول کد معرف.',
	'Each row depth must match the number of rates, rates must sum to tree_cap, and the seller (first rate) must be highest.' => 'عمق هر ردیف باید با تعداد نرخ‌ها برابر باشد، مجموع نرخ‌ها باید برابر سقف درخت باشد و نرخ فروشنده (اولین نرخ) باید بیشترین باشد.',
	'Depth'                                                       => 'عمق',
	'Tree cap'                                                    => 'سقف درخت',
	'Rates (basis-10000, seller → upline)'                        => 'نرخ‌ها (مبنای ۱۰۰۰۰، فروشنده ← بالادست)',
	'Tier %d'                                                     => 'سطح %d',
	'Using first %1$d rate(s); sum = %2$d (must equal tree cap).'  => 'از %1$d نرخ اول استفاده می‌شود؛ مجموع = %2$d (باید برابر سقف درخت باشد).',
	'Current payable total: %s Rial'                              => 'مجموع قابل پرداخت فعلی: %s ریال',
	'Period start'                                                => 'شروع دوره',
	'Period end'                                                  => 'پایان دوره',
	'Prepare draft batch'                                         => 'آماده‌سازی بسته پیش‌نویس',
	'ID'                                                          => 'شناسه',
	'Period'                                                      => 'دوره',
	'Total'                                                       => 'مجموع',
	'Status'                                                      => 'وضعیت',
	'Actions'                                                     => 'اقدامات',
	'No settlements yet.'                                         => 'هنوز تسویه‌ای نیست.',
	'Mark reviewed'                                               => 'علامت‌گذاری بررسی‌شده',
	'Approve'                                                     => 'تأیید',
	'Reject'                                                      => 'رد',
	'Mark paid'                                                   => 'علامت‌گذاری پرداخت‌شده',
	'Settlements'                                                 => 'تسویه‌ها',
	'Withdrawals'                                                 => 'برداشت‌ها',
	'Affiliates'                                                  => 'افیلیت‌ها',
	'Fraud queue'                                                 => 'صف تقلب',
	'Bonus plans'                                                 => 'پلن‌های پاداش',
	'Reports'                                                     => 'گزارش‌ها',
	'Affiliate'                                                   => 'افیلیت',
	'Amount'                                                      => 'مبلغ',
	'IBAN'                                                        => 'شبا',
	'No withdrawals yet.'                                         => 'هنوز برداشتی نیست.',
	'Unauthorized.'                                               => 'دسترسی غیرمجاز.',
	'User'                                                        => 'کاربر',
	'Type'                                                        => 'نوع',
	'Recruit'                                                     => 'جذب',
	'No affiliates yet.'                                          => 'هنوز افیلیتی نیست.',
	'yes'                                                         => 'بله',
	'no'                                                          => 'خیر',
	'Make staff'                                                  => 'تبدیل به پرسنل',
	'Make affiliate'                                              => 'تبدیل به افیلیت',
	'Event'                                                       => 'رویداد',
	'Severity'                                                    => 'شدت',
	'Order'                                                       => 'سفارش',
	'No unreviewed events.'                                       => 'رویداد بررسی‌نشده‌ای نیست.',
	'Title'                                                       => 'عنوان',
	'Metric'                                                      => 'معیار',
	'Sales volume'                                                => 'حجم فروش',
	'Order count'                                                 => 'تعداد سفارش',
	'Threshold'                                                   => 'آستانه',
	'Reward type'                                                 => 'نوع پاداش',
	'Fixed'                                                       => 'ثابت',
	'Rate (basis-10000)'                                          => 'نرخ (مبنای ۱۰۰۰۰)',
	'Reward value'                                                => 'مقدار پاداش',
	'Create plan'                                                 => 'ایجاد پلن',
	'Reward'                                                      => 'پاداش',
	'No active plans.'                                            => 'پلن فعالی نیست.',
	'Referral discount'                                           => 'تخفیف معرف',
	'Affiliate dashboard'                                         => 'داشبورد افیلیت',
	'Please log in to register as an affiliate.'                  => 'برای ثبت‌نام به‌عنوان افیلیت وارد شوید.',
	'Please log in to view your affiliate dashboard.'             => 'برای مشاهده داشبورد افیلیت وارد شوید.',
	'National ID'                                                 => 'کد ملی',
	'Referral code (optional)'                                    => 'کد معرف (اختیاری)',
	'Submit registration'                                         => 'ارسال ثبت‌نام',
	'Request withdrawal'                                          => 'درخواست برداشت',
	'Amount (Rial)'                                               => 'مبلغ (ریال)',
	'Submit request'                                              => 'ثبت درخواست',
	'Recent withdrawals'                                          => 'برداشت‌های اخیر',
	'Referral link:'                                              => 'لینک معرف:',
	'You are already registered (status: %s).'                    => 'شما قبلاً ثبت‌نام کرده‌اید (وضعیت: %s).',
	'Pending: %s'                                                 => 'در انتظار: %s',
	'Payable: %s'                                                 => 'قابل پرداخت: %s',
	'Withdrawable: %s'                                            => 'قابل برداشت: %s',
	'Available to request: %s'                                    => 'قابل درخواست: %s',
	'Recruitment: enabled'                                        => 'مجوز جذب: فعال',
	'Recruitment: locked'                                         => 'مجوز جذب: قفل',
	'Approved affiliate account required.'                        => 'حساب افیلیت تأییدشده لازم است.',
	'Registration submitted. Waiting for admin approval.'         => 'ثبت‌نام ارسال شد. در انتظار تأیید مدیر.',
	'You are already registered as an affiliate.'                 => 'شما قبلاً به‌عنوان افیلیت ثبت‌نام کرده‌اید.',
	'Invalid national ID.'                                        => 'کد ملی نامعتبر است.',
	'This national ID is already registered.'                     => 'این کد ملی قبلاً ثبت شده است.',
	'Registration failed. Please try again.'                      => 'ثبت‌نام ناموفق بود. دوباره تلاش کنید.',
	'This referrer is not yet allowed to recruit.'                => 'این معرف هنوز مجاز به جذب نیست.',
	'Invalid request.'                                            => 'درخواست نامعتبر است.',
	'Security check failed.'                                      => 'بررسی امنیتی ناموفق بود.',
	'Affiliate cannot be approved from the current status.'       => 'افیلیت از وضعیت فعلی قابل تأیید نیست.',
	'Invalid withdrawal amount.'                                  => 'مبلغ برداشت نامعتبر است.',
	'Insufficient withdrawable balance.'                          => 'موجودی قابل برداشت کافی نیست.',
	'Could not create withdrawal request.'                        => 'ایجاد درخواست برداشت ممکن نشد.',
	'Withdrawal cannot be approved.'                              => 'این برداشت قابل تأیید نیست.',
	'Could not lock withdrawal amount.'                           => 'قفل کردن مبلغ برداشت ممکن نشد.',
	'Could not approve withdrawal.'                               => 'تأیید برداشت ممکن نشد.',
	'Withdrawal cannot be rejected.'                              => 'این برداشت قابل رد نیست.',
	'Withdrawal cannot be marked paid.'                           => 'این برداشت را نمی‌توان پرداخت‌شده علامت زد.',
	'Could not mark withdrawal as paid.'                          => 'علامت‌گذاری برداشت به‌عنوان پرداخت‌شده ممکن نشد.',
	'Affiliate account required.'                                 => 'حساب افیلیت لازم است.',
	'Withdrawal requested.'                                       => 'درخواست برداشت ثبت شد.',
	'Report sections'                                             => 'بخش‌های گزارش',
	'Referral tree'                                               => 'درخت معرف',
	'All'                                                         => 'همه',
	'Kind'                                                        => 'نوع',
	'Beneficiary ID'                                              => 'شناسه ذی‌نفع',
	'Order ID'                                                    => 'شناسه سفارش',
	'From'                                                        => 'از',
	'To'                                                          => 'تا',
	'Filter'                                                      => 'فیلتر',
	'Beneficiary'                                                 => 'ذی‌نفع',
	'Tier'                                                        => 'سطح',
	'Rate'                                                        => 'نرخ',
	'Window ends'                                                 => 'پایان پنجره',
	'Created'                                                     => 'ایجاد',
	'No commissions match these filters.'                         => 'پورسانتی با این فیلترها یافت نشد.',
	'Period overlaps from'                                        => 'همپوشانی دوره از',
	'Period overlaps to'                                          => 'همپوشانی دوره تا',
	'Approved by'                                                 => 'تأییدکننده',
	'Approved at'                                                 => 'زمان تأیید',
	'Ops'                                                         => 'عملیات',
	'No settlements match these filters.'                         => 'تسویه‌ای با این فیلترها یافت نشد.',
	'Open settlements'                                            => 'مشاهده تسویه‌ها',
	'Affiliate ID'                                                => 'شناسه افیلیت',
	'Requested'                                                   => 'درخواست‌شده',
	'Requested at'                                                => 'زمان درخواست',
	'Processed'                                                   => 'پردازش',
	'Note'                                                        => 'یادداشت',
	'No withdrawals match these filters.'                         => 'برداشتی با این فیلترها یافت نشد.',
	'Open withdrawals'                                            => 'مشاهده برداشت‌ها',
	'Inspect node'                                                => 'بررسی گره',
	'Show roots'                                                  => 'نمایش ریشه‌ها',
	'Node #%1$s · depth %2$s · path %3$s · type %4$s · status %5$s · user %6$s' => 'گره #%1$s · عمق %2$s · مسیر %3$s · نوع %4$s · وضعیت %5$s · کاربر %6$s',
	'Upline (closest → root):'                                    => 'بالادست (نزدیک‌ترین ← ریشه):',
	'Direct children'                                             => 'فرزندان مستقیم',
	'No children.'                                                => 'فرزندی نیست.',
	'Subtree'                                                     => 'زیردرخت',
	'user %1$s · %2$s · %3$s · depth %4$s'                        => 'کاربر %1$s · %2$s · %3$s · عمق %4$s',
	'Root affiliates'                                             => 'افیلیت‌های ریشه',
	'Path'                                                        => 'مسیر',
	'Children'                                                    => 'فرزندان',
	'No tree nodes yet.'                                          => 'هنوز گره‌ای در درخت نیست.',
	'%1$s rows · filtered sum %2$s Rial'                          => '%1$s ردیف · مجموع فیلترشده %2$s ریال',
	'Rial'                                                        => 'ریال',
	'Export CSV'                                                  => 'خروجی CSV',
	'%s items'                                                    => '%s مورد',
	'Pending'                                                     => 'در انتظار',
	'Payable'                                                     => 'قابل پرداخت',
	'Paid'                                                        => 'پرداخت‌شده',
	'Void'                                                        => 'باطل',
	'Approved'                                                    => 'تأییدشده',
	'Rejected'                                                    => 'ردشده',
	'Draft'                                                       => 'پیش‌نویس',
	'Reviewed'                                                    => 'بررسی‌شده',
	'Staff'                                                       => 'پرسنل',
	'Tree'                                                        => 'درخت',
	'Staff override'                                              => 'سهم پرسنل',
	'Bonus'                                                       => 'پاداش',
	'Self-purchase'                                               => 'خرید با کد خود',
	'Own referral chain'                                          => 'خرید در زنجیره خود',
	'IP seen'                                                     => 'مشاهده آی‌پی',
	'Critical'                                                    => 'بحرانی',
	'Info'                                                        => 'اطلاع',
	'Matrix must have at least one row.'                          => 'ماتریس باید حداقل یک ردیف داشته باشد.',
	'Row %d is invalid.'                                          => 'ردیف %d نامعتبر است.',
	'Row %d: depth is %d but %d rates provided.'                  => 'ردیف %d: عمق %d است اما %d نرخ وارد شده.',
	'Row %d: rates sum to %d but tree cap is %d.'                 => 'ردیف %d: مجموع نرخ‌ها %d است اما سقف درخت %d است.',
	'Row %d: direct seller must have the highest rate.'           => 'ردیف %d: فروشنده مستقیم باید بیشترین نرخ را داشته باشد.',
	'Identity verification failed.'                               => 'احراز هویت ناموفق بود.',
	'Bonus plan title is required.'                               => 'عنوان پلن پاداش الزامی است.',
	'Invalid bonus metric.'                                       => 'معیار پاداش نامعتبر است.',
	'Invalid reward type.'                                        => 'نوع پاداش نامعتبر است.',
	'Could not create bonus plan.'                                => 'ایجاد پلن پاداش ممکن نشد.',
	'Affiliate already exists in the tree.'                       => 'این افیلیت از قبل در درخت وجود دارد.',
	'Parent affiliate not found in tree.'                         => 'افیلیت والد در درخت یافت نشد.',
	'Referral loop detected.'                                     => 'حلقه معرف شناسایی شد.',
	'Failed to insert into tree.'                                 => 'درج در درخت ناموفق بود.',
	'Could not generate unique code.'                             => 'تولید کد یکتا ممکن نشد.',
	'Self-purchase referrals are not allowed.'                    => 'خرید با کد معرف خودتان مجاز نیست.',
	'Purchases inside your own referral chain are not allowed.'   => 'خرید داخل زنجیره معرف خودتان مجاز نیست.',
	'Invalid settlement period.'                                  => 'دوره تسویه نامعتبر است.',
	'Could not create settlement batch.'                          => 'ایجاد بسته تسویه ممکن نشد.',
	'Settlement not found.'                                       => 'تسویه یافت نشد.',
	'Settlement cannot be approved from the current status.'      => 'تسویه از وضعیت فعلی قابل تأیید نیست.',
	'Invalid settlement status transition.'                       => 'تغییر وضعیت تسویه نامعتبر است.',
);

$dir = dirname( __DIR__ ) . '/languages';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0775, true );
}

$header = "msgid \"\"\nmsgstr \"\"\n"
	. "\"Project-Id-Version: Zanjir 2.3.0\\n\"\n"
	. "\"Language: fa_IR\\n\"\n"
	. "\"MIME-Version: 1.0\\n\"\n"
	. "\"Content-Type: text/plain; charset=UTF-8\\n\"\n"
	. "\"Content-Transfer-Encoding: 8bit\\n\"\n"
	. "\"Plural-Forms: nplurals=2; plural=(n > 1);\\n\"\n\n";

$po = $header;
foreach ( $entries as $en => $fa ) {
	$po .= 'msgid "' . addcslashes( $en, "\"\\\n\r\t" ) . "\"\n";
	$po .= 'msgstr "' . addcslashes( $fa, "\"\\\n\r\t" ) . "\"\n\n";
}
file_put_contents( $dir . '/zanjir-fa_IR.po', $po );

$pot = $header;
$pot = str_replace( 'Language: fa_IR', 'Language: ', $pot );
foreach ( array_keys( $entries ) as $en ) {
	$pot .= 'msgid "' . addcslashes( $en, "\"\\\n\r\t" ) . "\"\nmsgstr \"\"\n\n";
}
file_put_contents( $dir . '/zanjir.pot', $pot );

$keys = array_merge( array( '' ), array_keys( $entries ) );
$vals = array_merge( array( '' ), array_values( $entries ) );
$n    = count( $keys );

$orig_data  = '';
$trans_data = '';
$orig_meta  = array();
$trans_meta = array();

foreach ( $keys as $i => $k ) {
	$orig_meta[]  = array( strlen( $k ), strlen( $orig_data ) );
	$orig_data   .= $k . "\0";
	$trans_meta[] = array( strlen( $vals[ $i ] ), strlen( $trans_data ) );
	$trans_data  .= $vals[ $i ] . "\0";
}

$o_offset    = 28;
$t_offset    = 28 + $n * 8;
$orig_start  = 28 + $n * 16;
$trans_start = $orig_start + strlen( $orig_data );

$mo  = pack( 'V*', 0x950412de, 0, $n, $o_offset, $t_offset, 0, $orig_start );
foreach ( $orig_meta as $m ) {
	$mo .= pack( 'VV', $m[0], $orig_start + $m[1] );
}
foreach ( $trans_meta as $m ) {
	$mo .= pack( 'VV', $m[0], $trans_start + $m[1] );
}
$mo .= $orig_data . $trans_data;

file_put_contents( $dir . '/zanjir-fa_IR.mo', $mo );

echo 'Wrote ' . count( $entries ) . " translations to languages/\n";
