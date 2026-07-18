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

		$len    = (int) Zanjir_Settings::get( 'affiliate_code_len', 8 );
		$chars  = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
		$table  = self::table();

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
			"SELECT * FROM " . self::table() . " WHERE affiliate_id = %d AND active = 1 LIMIT 1",
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
			"SELECT affiliate_id FROM " . self::table() . " WHERE code = %s AND active = 1",
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
		if ( is_user_logged_in() ) {
			return;
		}

		setcookie( self::COOKIE_NAME, sanitize_text_field( $code ), time() + self::COOKIE_EXPIRY, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
	}

	/**
	 * Get the tracked affiliate ID from cookie or current user.
	 *
	 * @return int|false
	 */
	public static function get_tracked_affiliate() {
		if ( is_user_logged_in() ) {
			$row = Zanjir_Registration::get_affiliate_by_user( get_current_user_id() );
			return $row ? (int) $row->id : false;
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$code = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) );
			return self::lookup_affiliate( $code );
		}

		return false;
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
	 * Hook: attach referral code to WooCommerce order at checkout.
	 *
	 * @param int $order_id
	 */
	public static function attach_to_order( $order_id ) {
		$affiliate_id = self::get_tracked_affiliate();
		if ( ! $affiliate_id ) {
			return;
		}

		$row = self::get_by_affiliate( $affiliate_id );
		if ( $row ) {
			update_post_meta( $order_id, '_zanjir_referral_code', $row->code ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			update_post_meta( $order_id, '_zanjir_seller_id', $affiliate_id ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
	}
}
