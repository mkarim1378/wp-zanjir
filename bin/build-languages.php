<?php
/**
 * One-off script to generate languages/*.po/*.pot/*.mo — run: php bin/build-languages.php
 *
 * @package Zanjir
 */

$entries = array(
	'Zanjir Settings'                                 => 'تنظیمات زنجیر',
	'Commission'                                      => 'پورسانت',
	'Tree Depth'                                      => 'عمق درخت',
	'Settlements'                                     => 'تسویه‌ها',
	'Withdrawals'                                     => 'برداشت‌ها',
	'Affiliates'                                      => 'افیلیت‌ها',
	'Fraud queue'                                     => 'صف تقلب',
	'Bonus plans'                                     => 'پلن‌های پاداش',
	'Settings'                                        => 'تنظیمات',
	'Referral discount'                               => 'تخفیف معرف',
	'Affiliate dashboard'                             => 'داشبورد افیلیت',
	'Please log in to register as an affiliate.'      => 'برای ثبت‌نام به‌عنوان افیلیت وارد شوید.',
	'Please log in to view your affiliate dashboard.' => 'برای مشاهده داشبورد افیلیت وارد شوید.',
	'National ID'                                     => 'کد ملی',
	'Referral code (optional)'                        => 'کد معرف (اختیاری)',
	'Submit registration'                             => 'ارسال ثبت‌نام',
	'Request withdrawal'                              => 'درخواست برداشت',
	'Amount (Rial)'                                   => 'مبلغ (ریال)',
	'Submit request'                                  => 'ثبت درخواست',
	'Recent withdrawals'                              => 'برداشت‌های اخیر',
	'Referral link:'                                  => 'لینک معرف:',
	'Commission matrix'                               => 'ماتریس پورسانت',
	'Operations'                                      => 'عملیات',
	'Refund window (days)'                            => 'پنجره مرجوعی (روز)',
	'Enable Referral Discount'                        => 'فعال‌سازی تخفیف معرف',
	'Coupon Compatibility'                            => 'سازگاری با کوپن',
	'Double-Dip (Discount + Commission)'              => 'دابل‌دیپ (تخفیف + پورسانت)',
	'Make staff'                                      => 'تبدیل به پرسنل',
	'Make affiliate'                                  => 'تبدیل به افیلیت',
	'Approve'                                         => 'تأیید',
	'Reject'                                          => 'رد',
	'Mark paid'                                       => 'علامت‌گذاری پرداخت‌شده',
	'Mark reviewed'                                   => 'علامت‌گذاری بررسی‌شده',
	'Prepare draft batch'                             => 'آماده‌سازی بسته پیش‌نویس',
	'No settlements yet.'                             => 'هنوز تسویه‌ای نیست.',
	'No withdrawals yet.'                             => 'هنوز برداشتی نیست.',
	'Approved affiliate account required.'            => 'حساب افیلیت تأییدشده لازم است.',
	'Registration submitted. Waiting for admin approval.' => 'ثبت‌نام ارسال شد. در انتظار تأیید مدیر.',
	'Withdrawal requested.'                           => 'درخواست برداشت ثبت شد.',
	'Recruitment: enabled'                            => 'مجوز جذب: فعال',
	'Recruitment: locked'                             => 'مجوز جذب: قفل',
	'Annual recruit cap (Rial)'                       => 'سقف جذب سالانه (ریال)',
	'Referral code length'                            => 'طول کد معرف',
	'Depth × position rates'                          => 'نرخ‌های عمق × جایگاه',
);

$dir = dirname( __DIR__ ) . '/languages';
if ( ! is_dir( $dir ) ) {
	mkdir( $dir, 0775, true );
}

$header = "msgid \"\"\nmsgstr \"\"\n"
	. "\"Project-Id-Version: Zanjir 2.1.0\\n\"\n"
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

$pot = str_replace( 'Language: fa_IR', 'Language: ', $po );
$pot = preg_replace( '/^msgstr "(.|\\n)*?"$/m', 'msgstr ""', $pot );
// Simpler pot: empty translations.
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
