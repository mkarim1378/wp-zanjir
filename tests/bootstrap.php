<?php
/**
 * PHPUnit bootstrap — lightweight stubs for WordPress-free unit tests.
 *
 * @package Zanjir
 */

define( 'ABSPATH', __DIR__ . '/../' );

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for unit tests.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		public $code;

		/**
		 * @var string
		 */
		public $message;

		/**
		 * @param string $code
		 * @param string $message
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * @param string $text
	 * @return string
	 */
	function esc_html__( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing
	 * @return bool
	 */
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @return bool
	 */
	function update_option() {
		return true;
	}
}

if ( ! function_exists( 'wp_parse_args' ) ) {
	/**
	 * @param array $args
	 * @param array $defaults
	 * @return array
	 */
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( $defaults, $args );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( $GLOBALS['zanjir_test_actions'] ) ) {
			$GLOBALS['zanjir_test_actions'] = array();
		}
		$GLOBALS['zanjir_test_actions'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * @param string   $hook
	 * @param callable $callback
	 * @param int      $priority
	 * @param int      $accepted_args
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( $GLOBALS['zanjir_test_filters'] ) ) {
			$GLOBALS['zanjir_test_filters'] = array();
		}
		$GLOBALS['zanjir_test_filters'][] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-zanjir-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-zanjir-national-id-validator.php';
require_once dirname( __DIR__ ) . '/includes/commission/class-zanjir-money.php';
require_once dirname( __DIR__ ) . '/includes/commission/class-zanjir-matrix.php';
require_once dirname( __DIR__ ) . '/includes/class-zanjir-loader.php';
