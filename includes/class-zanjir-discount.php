<?php
/**
 * Referral discount — cart fee + order meta.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Discount {

	/**
	 * Fee label used on cart/order.
	 */
	const FEE_ID = 'zanjir_referral_discount';

	/**
	 * Register WooCommerce hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public static function register( $loader ) {
		$loader->add_action( 'woocommerce_cart_calculate_fees', 'Zanjir_Discount', 'add_cart_fee', 20 );
		$loader->add_action( 'woocommerce_checkout_order_processed', 'Zanjir_Discount', 'apply_referral_discount', 30 );
		$loader->add_action( 'woocommerce_store_api_checkout_order_processed', 'Zanjir_Discount', 'apply_referral_discount', 30 );
	}

	/**
	 * Add a negative fee for the referral discount on the cart.
	 *
	 * @param WC_Cart $cart
	 */
	public static function add_cart_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( ! $cart || ! Zanjir_Settings::get( 'discount_enabled', 0 ) ) {
			return;
		}

		$amount = self::calculate_cart_discount( $cart );
		if ( $amount <= 0 ) {
			return;
		}

		$cart->add_fee(
			__( 'Referral discount', 'zanjir' ),
			-1 * $amount,
			false
		);
	}

	/**
	 * Compute referral discount for the current cart (Rial integer).
	 *
	 * @param WC_Cart|null $cart
	 * @return int
	 */
	public static function calculate_cart_discount( $cart = null ) {
		if ( ! $cart && function_exists( 'WC' ) && WC()->cart ) {
			$cart = WC()->cart;
		}
		if ( ! $cart ) {
			return 0;
		}

		$code_row = self::resolve_active_code_row();
		if ( ! $code_row ) {
			return 0;
		}

		$settings = Zanjir_Settings::all();
		$rate     = (int) $code_row->discount_rate;
		if ( $rate <= 0 ) {
			return 0;
		}

		$line_total = 0;
		foreach ( $cart->get_cart() as $item ) {
			$line_total += isset( $item['line_total'] ) ? (int) round( (float) $item['line_total'] ) : 0;
		}

		if ( $line_total <= 0 ) {
			return 0;
		}

		$coupon_discount = (int) round( (float) $cart->get_discount_total() );

		// When coupon_compat is off, do not stack referral discount with coupons.
		if ( empty( $settings['coupon_compat'] ) && $coupon_discount > 0 ) {
			return 0;
		}

		$referral = Zanjir_Money::amount_from_rate( $line_total, $rate );

		return Zanjir_Money::cap_referral_discount(
			$line_total,
			$referral,
			$coupon_discount,
			(int) $settings['max_discount']
		);
	}

	/**
	 * Resolve referral code row from cookie (approved + discount enabled).
	 *
	 * @return object|null
	 */
	private static function resolve_active_code_row() {
		$code = Zanjir_Referral_Code::get_cookie_code();
		if ( '' === $code ) {
			return null;
		}

		$affiliate_id = Zanjir_Referral_Code::lookup_affiliate( $code );
		if ( ! $affiliate_id ) {
			return null;
		}

		$affiliate = Zanjir_Registration::get_affiliate( $affiliate_id );
		if ( ! $affiliate || 'approved' !== $affiliate->status ) {
			return null;
		}

		// Self-purchase: no discount for buyer's own code.
		if ( is_user_logged_in() && (int) $affiliate->user_id === get_current_user_id() ) {
			return null;
		}

		$row = Zanjir_Referral_Code::get_by_affiliate( $affiliate_id );
		if ( ! $row || empty( $row->discount_enabled ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Persist referral discount meta on the order after checkout.
	 *
	 * Accepts either an order ID (shortcode checkout) or a WC_Order object (Store API / Checkout Block).
	 *
	 * @param int|WC_Order $order_id_or_order
	 */
	public static function apply_referral_discount( $order_id_or_order ) {
		$settings = Zanjir_Settings::all();

		if ( empty( $settings['discount_enabled'] ) ) {
			return;
		}

		if ( $order_id_or_order instanceof \WC_Order ) {
			$order    = $order_id_or_order;
			$order_id = $order->get_id();
		} else {
			$order_id = (int) $order_id_or_order;
			$order    = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}

		$fee_amount = self::get_order_referral_fee_amount( $order );
		if ( $fee_amount > 0 ) {
			$order->update_meta_data( '_zanjir_referral_discount', $fee_amount );
			$order->save();
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

		$coupon_discount = (int) round( (float) $order->get_discount_total() );
		if ( empty( $settings['coupon_compat'] ) && $coupon_discount > 0 ) {
			return;
		}

		$referral_discount = Zanjir_Money::cap_referral_discount(
			$total,
			Zanjir_Money::amount_from_rate( $total, $rate ),
			$coupon_discount,
			(int) $settings['max_discount']
		);

		if ( $referral_discount > 0 ) {
			$order->update_meta_data( '_zanjir_referral_discount', $referral_discount );
			$order->save();
		}
	}

	/**
	 * Absolute Rial amount of the referral fee on an order (if present).
	 *
	 * @param WC_Order $order
	 * @return int
	 */
	private static function get_order_referral_fee_amount( $order ) {
		$label = __( 'Referral discount', 'zanjir' );
		foreach ( $order->get_fees() as $fee ) {
			$name = $fee->get_name();
			if ( $name === $label || false !== stripos( $name, 'referral' ) || false !== stripos( $name, 'معرف' ) ) {
				return (int) abs( round( (float) $fee->get_total() ) );
			}
		}
		return 0;
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
		if ( $discount > 0 ) {
			return true;
		}

		return self::get_order_referral_fee_amount( $order ) > 0;
	}
}
