<?php
/**
 * Settings service with caching and defaults.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Settings {

	/**
	 * Option key in wp_options.
	 */
	const OPTION_KEY = 'zanjir_settings';

	/**
	 * @var array|null
	 */
	private static $cache;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'tree_depth'        => 3,
			'tree_cap'          => 2000,
			'staff_rate'        => 500,
			'bonus_pool'        => 500,
			'refund_window'     => 10,
			'discount_enabled'  => 0,
			'coupon_compat'     => 0,
			'double_dip'        => 0,
			'max_discount'      => 3000,
			'annual_cap'        => 50000000,
			'affiliate_code_len'=> 8,
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback value.
	 * @return mixed
	 */
	public static function get( $key = '', $default = null ) {
		$all = self::all();

		if ( '' === $key ) {
			return $all;
		}

		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Get all settings (cached).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored  = get_option( self::OPTION_KEY, array() );
		self::$cache = wp_parse_args( $stored, self::defaults() );

		return self::$cache;
	}

	/**
	 * Save settings (merge with defaults).
	 *
	 * @param array $values Key-value pairs to update.
	 * @return bool
	 */
	public static function update( array $values ) {
		$current = self::all();
		$merged  = array_merge( $current, $values );

		$result = update_option( self::OPTION_KEY, $merged );

		if ( $result ) {
			self::$cache = $merged;
		}

		return $result;
	}

	/**
	 * Reset to defaults.
	 *
	 * @return bool
	 */
	public static function reset() {
		self::$cache = null;
		return update_option( self::OPTION_KEY, self::defaults() );
	}

	/**
	 * Clear the in-memory cache.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
