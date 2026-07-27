<?php
/**
 * Referral discount handler — applies and caps referral discounts.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Discount {

	/**
	 * Apply referral discount meta to an order at checkout.
	 *
	 * Stores the computed Rial discount on the order. Cart fee application can
	 * be wired via WooCommerce fee hooks in a later UI pass.
	 *
	 * @param int $order_id
	 */
	public static function apply_referral_discount( $order_id ) {
		$settings = Zanjir_Settings::all();

		if ( empty( $settings['discount_enabled'] ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$seller_id = (int) $order->get_meta( '_zanjir_seller_id' );
		if ( ! $seller_id ) {
			return;
		}

		$code_row = Zanjir_Referral_Code::get_by_affiliate( $seller_id );
		if ( ! $code_row || empty( $code_row->discount_enabled ) ) {
			return;
		}

		$rate = (int) $code_row->discount_rate;
		if ( $rate <= 0 ) {
			return;
		}

		$total = 0;
		foreach ( $order->get_items() as $item ) {
			$total += (int) round( (float) $item->get_total() );
		}

		$referral_discount = Zanjir_Money::amount_from_rate( $total, $rate );
		$coupon_discount   = (int) round( (float) $order->get_discount_total() );
		$max_discount      = (int) $settings['max_discount'];

		$referral_discount = Zanjir_Money::cap_referral_discount(
			$total,
			$referral_discount,
			$coupon_discount,
			$max_discount
		);

		if ( $referral_discount > 0 ) {
			$order->update_meta_data( '_zanjir_referral_discount', $referral_discount );
			$order->save();
		}
	}

	/**
	 * Check if commission should be skipped due to double-dip being off.
	 *
	 * @param int $order_id
	 * @return bool True if commission should be skipped.
	 */
	public static function should_skip_commission( $order_id ) {
		$settings = Zanjir_Settings::all();

		if ( ! empty( $settings['double_dip'] ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		$discount = (int) $order->get_meta( '_zanjir_referral_discount' );

		return $discount > 0;
	}
}
