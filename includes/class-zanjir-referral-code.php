<?php
/**
 * Referral code generation and tracking.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Referral_Code {

	/**
	 * Cookie name for referral tracking.
	 */
	const COOKIE_NAME = 'zanjir_ref';

	/**
	 * Cookie duration in seconds (30 days).
	 */
	const COOKIE_EXPIRY = 2592000;

	/**
	 * Get the referral codes table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_referral_codes';
	}

	/**
	 * Generate a unique referral code for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return string|WP_Error The generated code or error.
	 */
	public static function generate( $affiliate_id ) {
		global $wpdb;

		$existing = self::get_by_affiliate( $affiliate_id );
		if ( $existing ) {
			return $existing->code;
		}

		$len   = (int) Zanjir_Settings::get( 'affiliate_code_len', 8 );
		$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$table = self::table();

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$code = '';
			for ( $i = 0; $i < $len; $i++ ) {
				$code .= $chars[ random_int( 0, strlen( $chars ) - 1 ) ];
			}

			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE code = %s", $code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $exists ) {
				$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					array(
						'affiliate_id'     => $affiliate_id,
						'code'             => $code,
						'discount_enabled' => 0,
						'discount_rate'    => 0,
						'active'           => 1,
						'created_at'       => current_time( 'mysql', true ),
					),
					array( '%d', '%s', '%d', '%d', '%d', '%s' )
				);

				return $code;
			}
		}

		return new WP_Error( 'code_generation_failed', __( 'Could not generate unique code.', 'zanjir' ) );
	}

	/**
	 * Get the active referral code for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return object|null
	 */
	public static function get_by_affiliate( $affiliate_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE affiliate_id = %d AND active = 1 LIMIT 1',
			$affiliate_id
		) );
	}

	/**
	 * Look up affiliate ID by referral code.
	 *
	 * @param string $code
	 * @return int|false Affiliate ID or false.
	 */
	public static function lookup_affiliate( $code ) {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT affiliate_id FROM ' . self::table() . ' WHERE code = %s AND active = 1',
			sanitize_text_field( $code )
		) );

		return $row ? (int) $row->affiliate_id : false;
	}

	/**
	 * Get the referral link URL for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return string|false
	 */
	public static function get_link( $affiliate_id ) {
		$row = self::get_by_affiliate( $affiliate_id );
		if ( ! $row ) {
			return false;
		}

		return add_query_arg( 'ref', $row->code, home_url( '/' ) );
	}

	/**
	 * Set referral cookie from a code.
	 *
	 * @param string $code
	 */
	public static function set_cookie( $code ) {
		setcookie(
			self::COOKIE_NAME,
			sanitize_text_field( $code ),
			array(
				'expires'  => time() + self::COOKIE_EXPIRY,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		// Available in the same request for checkout that lands with ?ref=.
		$_COOKIE[ self::COOKIE_NAME ] = sanitize_text_field( $code );
	}

	/**
	 * Read the referral code from cookie.
	 *
	 * @return string
	 */
	public static function get_cookie_code() {
		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return '';
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	}

	/**
	 * Hook: capture referral code from URL and set cookie.
	 */
	public static function maybe_capture_referral() {
		if ( ! isset( $_GET['ref'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$code = sanitize_text_field( wp_unslash( $_GET['ref'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$aff  = self::lookup_affiliate( $code );

		if ( $aff ) {
			self::set_cookie( $code );
		}
	}

	/**
	 * Hook: attach referral code from cookie to WooCommerce order at checkout.
	 *
	 * Accepts either an order ID (shortcode checkout) or a WC_Order object (Store API / Checkout Block).
	 *
	 * @param int|WC_Order $order_id_or_order
	 */
	public static function attach_to_order( $order_id_or_order ) {
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

		if ( $order->get_meta( '_zanjir_seller_id' ) ) {
			return;
		}

		$code = self::get_cookie_code();
		if ( '' === $code ) {
			return;
		}

		$affiliate_id = self::lookup_affiliate( $code );
		if ( ! $affiliate_id ) {
			return;
		}

		$affiliate = Zanjir_Registration::get_affiliate( $affiliate_id );
		if ( ! $affiliate || 'approved' !== $affiliate->status ) {
			return;
		}

		// Do not attribute self-purchases to the buyer's own code.
		$buyer_id = (int) $order->get_user_id();
		if ( $buyer_id && (int) $affiliate->user_id === $buyer_id ) {
			if ( class_exists( 'Zanjir_Fraud_Guard' ) ) {
				Zanjir_Fraud_Guard::log( 'self_buy', 'critical', $order_id, $affiliate_id, array( 'buyer_id' => $buyer_id ) );
			}
			return;
		}

		$check = class_exists( 'Zanjir_Fraud_Guard' )
			? Zanjir_Fraud_Guard::precheck_order( $order_id, $affiliate_id )
			: true;
		if ( is_wp_error( $check ) ) {
			return;
		}

		$order->update_meta_data( '_zanjir_referral_code', $code );
		$order->update_meta_data( '_zanjir_seller_id', $affiliate_id );
		$order->save();
	}
}
