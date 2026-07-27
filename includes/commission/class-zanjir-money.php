<?php
/**
 * Pure integer money helpers (testable without WordPress bootstrap).
 *
 * @package Zanjir\Commission
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Money {

	/**
	 * floor( base * rate / 10000 ) using integer division.
	 *
	 * @param int $base Amount in Rial.
	 * @param int $rate Basis-10000 rate.
	 * @return int
	 */
	public static function amount_from_rate( $base, $rate ) {
		$base = (int) $base;
		$rate = (int) $rate;

		if ( $base <= 0 || $rate <= 0 ) {
			return 0;
		}

		return (int) intdiv( $base * $rate, 10000 );
	}

	/**
	 * Cap referral discount so referral + coupon do not exceed max rate of line total.
	 *
	 * @param int $line_total         Sum of line totals (Rial).
	 * @param int $referral_discount  Proposed referral discount (Rial).
	 * @param int $coupon_discount    WooCommerce coupon discount (Rial).
	 * @param int $max_discount_rate  Cap as basis-10000 of line total.
	 * @return int Adjusted referral discount (Rial).
	 */
	public static function cap_referral_discount( $line_total, $referral_discount, $coupon_discount, $max_discount_rate ) {
		$line_total        = (int) $line_total;
		$referral_discount = max( 0, (int) $referral_discount );
		$coupon_discount   = max( 0, (int) $coupon_discount );
		$max_discount_rate = (int) $max_discount_rate;

		if ( $referral_discount <= 0 ) {
			return 0;
		}

		if ( $max_discount_rate <= 0 || $line_total <= 0 ) {
			return $referral_discount;
		}

		$max_amount = self::amount_from_rate( $line_total, $max_discount_rate );
		if ( $max_amount <= 0 ) {
			return $referral_discount;
		}

		$total = $referral_discount + $coupon_discount;
		if ( $total <= $max_amount ) {
			return $referral_discount;
		}

		return max( 0, $max_amount - $coupon_discount );
	}

	/**
	 * Commission base from line total minus discounts.
	 *
	 * @param int $line_total
	 * @param int $referral_discount
	 * @param int $coupon_discount
	 * @return int
	 */
	public static function commission_base( $line_total, $referral_discount, $coupon_discount ) {
		$base = (int) $line_total - (int) $referral_discount - (int) $coupon_discount;
		return max( 0, $base );
	}
}
