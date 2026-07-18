<?php
/**
 * Commission lifecycle — status transitions and return window cron.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Commission_Lifecycle {

	/**
	 * Cron hook name.
	 */
	const CRON_HOOK = 'zanjir_check_return_window';

	/**
	 * Register hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'woocommerce_order_status_completed', $this, 'on_order_completed' );
		$loader->add_action( self::CRON_HOOK, $this, 'check_return_window' );
	}

	/**
	 * Hook: schedule return window check when order is completed.
	 *
	 * @param int $order_id
	 */
	public function on_order_completed( $order_id ) {
		$snapshot = Zanjir_Order_Observer::get_snapshot( $order_id );
		if ( ! $snapshot ) {
			return;
		}

		$this->schedule_check( $order_id );
	}

	/**
	 * Schedule a single cron event for the return window check.
	 *
	 * @param int $order_id
	 */
	private function schedule_check( $order_id ) {
		$window = (int) Zanjir_Settings::get( 'refund_window', 10 );
		$delay  = $window * DAY_IN_SECONDS;

		if ( ! wp_next_scheduled( self::CRON_HOOK, array( $order_id ) ) ) {
			wp_schedule_single_event( time() + $delay, self::CRON_HOOK, array( $order_id ) );
		}
	}

	/**
	 * Hook: check return window and transition pending commissions to payable.
	 *
	 * @param int $order_id
	 */
	public function check_return_window( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( $order->has_status( 'refunded' ) ) {
			$this->void_commissions( $order_id );
			return;
		}

		$this->transition_to_payable( $order_id );
	}

	/**
	 * Transition all pending commissions for an order to payable.
	 *
	 * @param int $order_id
	 * @return int Number of rows updated.
	 */
	public function transition_to_payable( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'     => 'payable',
				'updated_at' => $now,
			),
			array(
				'order_id' => $order_id,
				'status'   => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( $updated ) {
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id, beneficiary_id, amount FROM {$table} WHERE order_id = %d AND status = 'payable'",
				$order_id
			) );

			foreach ( $rows as $row ) {
				/**
				 * Fires when a commission transitions to payable.
				 *
				 * @param int $commission_id
				 * @param int $order_id
				 * @param int $beneficiary_id
				 * @param int $amount
				 */
				do_action( 'zanjir_commission_payable', (int) $row->id, $order_id, (int) $row->beneficiary_id, (int) $row->amount );
			}
		}

		return $updated;
	}

	/**
	 * Void all commissions for an order (refund case).
	 *
	 * @param int $order_id
	 * @return int Number of rows voided.
	 */
	public function void_commissions( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'status'     => 'void',
				'updated_at' => $now,
			),
			array(
				'order_id' => $order_id,
				'status'   => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( $updated ) {
			$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				"SELECT id, beneficiary_id, amount FROM {$table} WHERE order_id = %d AND status = 'void'",
				$order_id
			) );

			foreach ( $rows as $row ) {
				/**
				 * Fires when a commission is voided.
				 *
				 * @param int $commission_id
				 * @param int $order_id
				 * @param int $beneficiary_id
				 * @param int $amount
				 */
				do_action( 'zanjir_commission_voided', (int) $row->id, $order_id, (int) $row->beneficiary_id, (int) $row->amount );
			}
		}

		return $updated;
	}

	/**
	 * Get pending commissions for an order.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public static function get_pending( $order_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}zanjir_commissions WHERE order_id = %d AND status = 'pending'",
			$order_id
		) );
	}

	/**
	 * Get all commissions for an order.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}zanjir_commissions WHERE order_id = %d",
			$order_id
		) );
	}
}
