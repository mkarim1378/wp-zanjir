<?php
/**
 * Recruit eligibility and identity verification hooks.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Recruit_Service {

	const CRON_HOOK = 'zanjir_recalc_annual_cap';

	/**
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( self::CRON_HOOK, $this, 'recalc_all' );
		$loader->add_action( 'zanjir_after_snapshot', $this, 'on_after_snapshot', 10, 3 );
	}

	/**
	 * Schedule daily recalc on demand.
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear scheduled cron.
	 */
	public static function clear_schedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * After snapshot: add base to seller annual_sales and refresh flag.
	 *
	 * @param int $order_id
	 * @param int $seller_id
	 * @param int $base
	 */
	public function on_after_snapshot( $order_id, $seller_id, $base ) {
		unset( $order_id );
		self::add_sales( (int) $seller_id, (int) $base );
		self::refresh_recruit_flag( (int) $seller_id );
	}

	/**
	 * Increment annual sales counter.
	 *
	 * @param int $affiliate_id
	 * @param int $amount
	 */
	public static function add_sales( $affiliate_id, $amount ) {
		global $wpdb;

		if ( $amount <= 0 || $affiliate_id <= 0 ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE {$wpdb->prefix}zanjir_affiliates
			 SET annual_sales = annual_sales + %d, updated_at = %s
			 WHERE id = %d",
			$amount,
			current_time( 'mysql', true ),
			$affiliate_id
		) );
	}

	/**
	 * Set recruit_enabled based on annual_cap.
	 *
	 * @param int $affiliate_id
	 * @return bool Enabled state.
	 */
	public static function refresh_recruit_flag( $affiliate_id ) {
		global $wpdb;

		$row = Zanjir_Registration::get_affiliate( $affiliate_id );
		if ( ! $row ) {
			return false;
		}

		$cap     = (int) Zanjir_Settings::get( 'annual_cap', 50000000 );
		$enabled = ( (int) $row->annual_sales >= $cap ) ? 1 : 0;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'zanjir_affiliates',
			array(
				'recruit_enabled' => $enabled,
				'updated_at'      => current_time( 'mysql', true ),
			),
			array( 'id' => $affiliate_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return (bool) $enabled;
	}

	/**
	 * Recalc all approved affiliates.
	 */
	public function recalc_all() {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}zanjir_affiliates WHERE status = 'approved'"
		);

		if ( ! $ids ) {
			return;
		}

		foreach ( $ids as $id ) {
			self::refresh_recruit_flag( (int) $id );
		}
	}

	/**
	 * Whether an affiliate may recruit downline.
	 *
	 * @param int $affiliate_id
	 * @return bool
	 */
	public static function can_recruit( $affiliate_id ) {
		$row = Zanjir_Registration::get_affiliate( $affiliate_id );
		return $row && ! empty( $row->recruit_enabled );
	}

	/**
	 * Run pluggable identity verification filter.
	 *
	 * @param string $national_id Raw national ID.
	 * @param int    $user_id
	 * @return true|WP_Error
	 */
	public static function verify_identity( $national_id, $user_id ) {
		/**
		 * Third-party identity verification (e.g. Shahkar).
		 *
		 * Return true, or WP_Error / false to reject.
		 *
		 * @param true|WP_Error|bool $result
		 * @param string             $national_id
		 * @param int                $user_id
		 */
		$result = apply_filters( 'zanjir_verify_identity', true, $national_id, $user_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( false === $result ) {
			return new WP_Error( 'identity_failed', __( 'Identity verification failed.', 'zanjir' ) );
		}

		return true;
	}
}
