<?php
/**
 * Front-end page access gate for the affiliate dashboard.
 *
 * Compatible with WooCommerce login and SMS login plugins that fire
 * voorodak_after_do_login / voorodak_after_do_register (e.g. Voorodak).
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Access_Gate {

	/**
	 * Cookie storing post-login redirect target.
	 */
	const REDIRECT_COOKIE = 'zanjir_login_redirect';

	/**
	 * @param Zanjir_Loader $loader Loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'template_redirect', $this, 'maybe_finish_login_redirect', 1 );
		$loader->add_action( 'template_redirect', $this, 'maybe_gate_dashboard' );
		$loader->add_filter( 'woocommerce_login_redirect', $this, 'filter_wc_login_redirect', 10, 2 );
		$loader->add_action( 'voorodak_after_do_login', $this, 'on_voorodak_after_login' );
		$loader->add_action( 'voorodak_after_do_register', $this, 'on_voorodak_after_register' );
	}

	/**
	 * Configured dashboard page URL, or empty string.
	 *
	 * @return string
	 */
	public static function dashboard_url() {
		return self::page_url( 'dashboard_page_id' );
	}

	/**
	 * Configured registration page URL, or empty string.
	 *
	 * @return string
	 */
	public static function register_url() {
		return self::page_url( 'register_page_id' );
	}

	/**
	 * Configured login page URL, or empty string.
	 *
	 * @return string
	 */
	public static function login_page_url() {
		return self::page_url( 'login_page_id' );
	}

	/**
	 * Permalink for a page setting key.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function page_url( $key ) {
		$id = (int) Zanjir_Settings::get( $key, 0 );
		if ( $id <= 0 ) {
			return '';
		}
		$url = get_permalink( $id );
		return $url ? (string) $url : '';
	}

	/**
	 * Remember where to send the user after login (cookie; works with AJAX SMS login).
	 *
	 * @param string $url Absolute URL.
	 */
	public static function remember_login_redirect( $url ) {
		$url = is_string( $url ) ? $url : '';
		$valid = $url ? wp_validate_redirect( $url, false ) : false;
		if ( ! $valid ) {
			return;
		}

		if ( headers_sent() ) {
			return;
		}

		$expire = time() + 10 * MINUTE_IN_SECONDS;
		$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
		$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

		setcookie( self::REDIRECT_COOKIE, $valid, $expire, $path, $domain, is_ssl(), true );
		$_COOKIE[ self::REDIRECT_COOKIE ] = $valid;
	}

	/**
	 * Read and clear the stored post-login redirect.
	 *
	 * @return string
	 */
	public static function consume_login_redirect() {
		$candidate = '';
		if ( ! empty( $_COOKIE[ self::REDIRECT_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$candidate = esc_url_raw( wp_unslash( $_COOKIE[ self::REDIRECT_COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( ! headers_sent() ) {
			$path   = defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/';
			$domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
			setcookie( self::REDIRECT_COOKIE, '', time() - YEAR_IN_SECONDS, $path, $domain, is_ssl(), true );
		}
		unset( $_COOKIE[ self::REDIRECT_COOKIE ] );

		if ( '' === $candidate ) {
			return '';
		}

		$valid = wp_validate_redirect( $candidate, false );
		return $valid ? $valid : '';
	}

	/**
	 * Peek at redirect target without clearing (query args + cookie).
	 *
	 * @return string
	 */
	public static function peek_login_redirect() {
		$candidate = '';
		if ( ! empty( $_REQUEST['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_REQUEST['redirect'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = esc_url_raw( wp_unslash( $_REQUEST['redirect'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} elseif ( ! empty( $_COOKIE[ self::REDIRECT_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$candidate = esc_url_raw( wp_unslash( $_COOKIE[ self::REDIRECT_COOKIE ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( '' === $candidate ) {
			return '';
		}

		$decoded = rawurldecode( $candidate );
		$valid   = wp_validate_redirect( $decoded, false );
		if ( ! $valid ) {
			$valid = wp_validate_redirect( $candidate, false );
		}

		return $valid ? $valid : '';
	}

	/**
	 * Login URL: configured login page, then WooCommerce My Account, then wp-login.
	 *
	 * @param string $redirect_to Absolute URL to return to after login.
	 * @return string
	 */
	public static function login_url( $redirect_to = '' ) {
		$redirect_to = is_string( $redirect_to ) ? $redirect_to : '';
		if ( $redirect_to ) {
			self::remember_login_redirect( $redirect_to );
		}

		$login_page = self::login_page_url();
		if ( $login_page ) {
			return $redirect_to
				? add_query_arg( 'redirect_to', $redirect_to, $login_page )
				: $login_page;
		}

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
		$candidate = self::peek_login_redirect();
		if ( $candidate ) {
			self::consume_login_redirect();
			return $candidate;
		}
		return $redirect;
	}

	/**
	 * After Voorodak SMS login: redirect when not in AJAX.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_voorodak_after_login( $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$target = self::peek_login_redirect();
		if ( ! $target ) {
			return;
		}

		// AJAX OTP flows cannot follow Location headers; keep cookie for next page load.
		if ( wp_doing_ajax() ) {
			self::remember_login_redirect( $target );
			return;
		}

		self::consume_login_redirect();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * After Voorodak SMS registration: prefer affiliate registration page.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_voorodak_after_register( $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$reg    = self::register_url();
		$target = $reg ? $reg : self::peek_login_redirect();
		if ( ! $target ) {
			return;
		}

		self::remember_login_redirect( $target );

		if ( wp_doing_ajax() ) {
			return;
		}

		self::consume_login_redirect();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Complete cookie-based redirect after AJAX SMS login (next full page load).
	 */
	public function maybe_finish_login_redirect() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( empty( $_COOKIE[ self::REDIRECT_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;
		}

		$target = self::consume_login_redirect();
		if ( ! $target ) {
			return;
		}

		$current = '';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$current = home_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( $current && untrailingslashit( $current ) === untrailingslashit( $target ) ) {
			return;
		}

		nocache_headers();
		wp_safe_redirect( $target );
		exit;
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
