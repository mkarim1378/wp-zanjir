<?php
/**
 * Front-end page access gate for the affiliate dashboard.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Access_Gate {

	/**
	 * @param Zanjir_Loader $loader Loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'template_redirect', $this, 'maybe_gate_dashboard' );
		$loader->add_filter( 'woocommerce_login_redirect', $this, 'filter_wc_login_redirect', 10, 2 );
	}

	/**
	 * Configured dashboard page URL, or empty string.
	 *
	 * @return string
	 */
	public static function dashboard_url() {
		$id = (int) Zanjir_Settings::get( 'dashboard_page_id', 0 );
		if ( $id <= 0 ) {
			return '';
		}
		$url = get_permalink( $id );
		return $url ? (string) $url : '';
	}

	/**
	 * Configured registration page URL, or empty string.
	 *
	 * @return string
	 */
	public static function register_url() {
		$id = (int) Zanjir_Settings::get( 'register_page_id', 0 );
		if ( $id <= 0 ) {
			return '';
		}
		$url = get_permalink( $id );
		return $url ? (string) $url : '';
	}

	/**
	 * Login URL (WooCommerce my-account when available).
	 *
	 * @param string $redirect_to Absolute URL to return to after login.
	 * @return string
	 */
	public static function login_url( $redirect_to = '' ) {
		$redirect_to = is_string( $redirect_to ) ? $redirect_to : '';

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$myaccount = wc_get_page_permalink( 'myaccount' );
			if ( $myaccount ) {
				if ( $redirect_to ) {
					return add_query_arg(
						array(
							'redirect'    => $redirect_to,
							'redirect_to' => $redirect_to,
						),
						$myaccount
					);
				}
				return $myaccount;
			}
		}

		return wp_login_url( $redirect_to );
	}

	/**
	 * Honor redirect query args after WooCommerce login.
	 *
	 * @param string  $redirect Default redirect.
	 * @param WP_User $user     Logged-in user.
	 * @return string
	 */
	public function filter_wc_login_redirect( $redirect, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$candidate = '';
		if ( ! empty( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_REQUEST['redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = esc_url_raw( wp_unslash( $_REQUEST['redirect'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $candidate ) {
			return $redirect;
		}

		// Values may arrive URL-encoded from the login form/query.
		$decoded = rawurldecode( $candidate );
		$valid   = wp_validate_redirect( $decoded, false );
		if ( ! $valid ) {
			$valid = wp_validate_redirect( $candidate, false );
		}

		return $valid ? $valid : $redirect;
	}

	/**
	 * Redirect guests and non-approved affiliates away from the dashboard page.
	 */
	public function maybe_gate_dashboard() {
		$dash_id = (int) Zanjir_Settings::get( 'dashboard_page_id', 0 );
		if ( $dash_id <= 0 || ! is_page( $dash_id ) ) {
			return;
		}

		nocache_headers();

		if ( Zanjir_Roles::can_manage() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			$dash_url = get_permalink( $dash_id );
			wp_safe_redirect( self::login_url( $dash_url ? $dash_url : '' ) );
			exit;
		}

		$affiliate = Zanjir_Registration::get_affiliate_by_user( get_current_user_id() );
		if ( $affiliate && 'approved' === $affiliate->status ) {
			return;
		}

		$reg_url = self::register_url();
		wp_safe_redirect( $reg_url ? $reg_url : home_url( '/' ) );
		exit;
	}
}
