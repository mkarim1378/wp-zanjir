<?php
/**
 * Referral discount handler — applies and caps referral discounts.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Discount {

	/**
	 * Apply referral discount to an order at checkout.
	 *
	 * @param int $order_id
	 */
	public static function apply_referral_discount( $order_id ) {
		$settings = Zanjir_Settings::all();

		if ( empty( $settings['discount_enabled'] ) ) {
			return;
		}

		$seller_id = get_post_meta( $order_id, '_zanjir_seller_id', true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $seller_id ) {
			return;
		}

		$code_row = Zanjir_Referral_Code::get_by_affiliate( (int) $seller_id );
		if ( ! $code_row || empty( $code_row->discount_enabled ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$rate = (int) $code_row->discount_rate;
		if ( $rate <= 0 ) {
			return;
		}

		$total = 0;
		foreach ( $order->get_items() as $item ) {
			$total += (float) $item->get_total();
		}

		$referral_discount = (int) round( $total * $rate / 10000 );

		$coupon_discount = (float) $order->get_discount_total();
		$max_discount    = (int) $settings['max_discount'];

		$total_discount = $referral_discount + (int) $coupon_discount;
		if ( $max_discount > 0 && $total_discount > $max_discount ) {
			$referral_discount = max( 0, $max_discount - (int) $coupon_discount );
		}

		if ( $referral_discount > 0 ) {
			update_post_meta( $order_id, '_zanjir_referral_discount', $referral_discount ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}

	/**
	 * Check if commission should be skipped due to double-dip being off.
	 *
	 * If double_dip is disabled and the order has a referral discount,
	 * commission is not calculated.
	 *
	 * @param int $order_id
	 * @return bool True if commission should be skipped.
	 */
	public static function should_skip_commission( $order_id ) {
		$settings = Zanjir_Settings::all();

		if ( ! empty( $settings['double_dip'] ) ) {
			return false;
		}

		$discount = (float) get_post_meta( $order_id, '_zanjir_referral_discount', true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return $discount > 0;
	}
}
