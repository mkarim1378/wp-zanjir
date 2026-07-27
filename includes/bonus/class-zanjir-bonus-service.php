<?php
/**
 * Bonus pool plans and periodic evaluation.
 *
 * @package Zanjir\Bonus
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Bonus_Service {

	const CRON_HOOK = 'zanjir_bonus_evaluate';

	/**
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( self::CRON_HOOK, $this, 'evaluate_active_plans' );
	}

	/**
	 * Schedule daily bonus evaluation.
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + ( 2 * HOUR_IN_SECONDS ), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Clear cron.
	 */
	public static function clear_schedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_bonus_plans';
	}

	/**
	 * Create a bonus plan.
	 *
	 * @param array $args
	 * @return int|WP_Error
	 */
	public static function create_plan( array $args ) {
		global $wpdb;

		$title = isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '';
		if ( '' === $title ) {
			return new WP_Error( 'invalid_title', __( 'Bonus plan title is required.', 'zanjir' ) );
		}

		$metric       = isset( $args['metric'] ) ? sanitize_key( $args['metric'] ) : 'sales_volume';
		$threshold    = isset( $args['threshold'] ) ? absint( $args['threshold'] ) : 0;
		$reward_type  = isset( $args['reward_type'] ) ? sanitize_key( $args['reward_type'] ) : 'fixed';
		$reward_value = isset( $args['reward_value'] ) ? absint( $args['reward_value'] ) : 0;
		$period_type  = isset( $args['period_type'] ) ? sanitize_key( $args['period_type'] ) : 'monthly';

		if ( ! in_array( $metric, array( 'sales_volume', 'order_count' ), true ) ) {
			return new WP_Error( 'invalid_metric', __( 'Invalid bonus metric.', 'zanjir' ) );
		}
		if ( ! in_array( $reward_type, array( 'fixed', 'rate' ), true ) ) {
			return new WP_Error( 'invalid_reward', __( 'Invalid reward type.', 'zanjir' ) );
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'title'        => $title,
				'metric'       => $metric,
				'threshold'    => $threshold,
				'reward_type'  => $reward_type,
				'reward_value' => $reward_value,
				'period_type'  => $period_type,
				'active'       => 1,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : new WP_Error( 'db_error', __( 'Could not create bonus plan.', 'zanjir' ) );
	}

	/**
	 * Active plans.
	 *
	 * @return array
	 */
	public static function list_active() {
		global $wpdb;

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE active = 1 ORDER BY id ASC'
		);
	}

	/**
	 * Evaluate plans and credit withdrawable bonus when threshold met.
	 *
	 * Uses affiliate annual_sales / order snapshots in a simple monthly window.
	 */
	public function evaluate_active_plans() {
		$plans = self::list_active();
		if ( empty( $plans ) ) {
			return;
		}

		$pool_rate = (int) Zanjir_Settings::get( 'bonus_pool', 500 );
		if ( $pool_rate <= 0 ) {
			return;
		}

		global $wpdb;
		$affiliates = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, annual_sales FROM {$wpdb->prefix}zanjir_affiliates WHERE status = 'approved'"
		);

		if ( ! $affiliates ) {
			return;
		}

		foreach ( $plans as $plan ) {
			foreach ( $affiliates as $aff ) {
				$metric_value = ( 'order_count' === $plan->metric )
					? self::count_orders_for_affiliate( (int) $aff->id )
					: (int) $aff->annual_sales;

				if ( $metric_value < (int) $plan->threshold ) {
					continue;
				}

				$reward = ( 'rate' === $plan->reward_type )
					? (int) intdiv( $metric_value * (int) $plan->reward_value, 10000 )
					: (int) $plan->reward_value;

				// Cap single reward by configured bonus pool rate against metric (sales).
				$max_from_pool = (int) intdiv( max( $metric_value, 1 ) * $pool_rate, 10000 );
				if ( $reward > $max_from_pool && $max_from_pool > 0 ) {
					$reward = $max_from_pool;
				}

				if ( $reward <= 0 ) {
					continue;
				}

				if ( self::already_paid_this_month( (int) $aff->id, (int) $plan->id ) ) {
					continue;
				}

				$commission_note_id = self::insert_bonus_commission( (int) $aff->id, $reward, (int) $plan->id );
				if ( $commission_note_id ) {
					Zanjir_Ledger::credit( (int) $aff->id, 'withdrawable', $reward, 'bonus', $commission_note_id, 'plan:' . (int) $plan->id );
				}
			}
		}
	}

	/**
	 * @param int $affiliate_id
	 * @return int
	 */
	private static function count_orders_for_affiliate( $affiliate_id ) {
		global $wpdb;

		$count = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COUNT(*) FROM {$wpdb->prefix}zanjir_order_snapshots WHERE seller_affiliate_id = %d",
			$affiliate_id
		) );

		return (int) $count;
	}

	/**
	 * Prevent duplicate bonus payouts in the same calendar month.
	 *
	 * @param int $affiliate_id
	 * @param int $plan_id
	 * @return bool
	 */
	private static function already_paid_this_month( $affiliate_id, $plan_id ) {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}zanjir_wallet_ledger
			 WHERE affiliate_id = %d AND ref_type = 'bonus' AND note = %s
			 AND created_at >= %s
			 LIMIT 1",
			$affiliate_id,
			'plan:' . $plan_id,
			gmdate( 'Y-m-01 00:00:00' )
		) );

		return ! empty( $found );
	}

	/**
	 * Insert a paid bonus commission row for audit.
	 *
	 * @param int $affiliate_id
	 * @param int $amount
	 * @param int $plan_id
	 * @return int|false
	 */
	private static function insert_bonus_commission( $affiliate_id, $amount, $plan_id ) {
		global $wpdb;

		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'zanjir_commissions',
			array(
				'order_id'       => 0,
				'snapshot_id'    => 0,
				'beneficiary_id' => $affiliate_id,
				'kind'           => 'bonus',
				'tier_level'     => null,
				'rate'           => 0,
				'amount'         => $amount,
				'status'         => 'paid',
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		unset( $plan_id );

		return $ok ? (int) $wpdb->insert_id : false;
	}
}
