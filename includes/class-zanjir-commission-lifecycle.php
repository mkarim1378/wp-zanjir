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
	 * Hook: create pending commissions and schedule return window check.
	 *
	 * @param int $order_id
	 */
	public function on_order_completed( $order_id ) {
		$snapshot = Zanjir_Order_Observer::get_snapshot( $order_id );
		if ( ! $snapshot ) {
			return;
		}

		$this->create_pending_commissions( $order_id, $snapshot );
		$this->schedule_check( $order_id );
	}

	/**
	 * Create pending commission rows from snapshot (idempotent).
	 *
	 * @param int    $order_id
	 * @param object $snapshot
	 * @return bool
	 */
	private function create_pending_commissions( $order_id, $snapshot ) {
		if ( Zanjir_Commission_Engine::has_commissions( $order_id ) ) {
			$this->ensure_return_window( $order_id );
			return false;
		}

		if ( Zanjir_Discount::should_skip_commission( $order_id ) ) {
			return false;
		}

		$window_ends = Zanjir_Commission_Engine::get_return_window_end( $order_id );
		$rows        = Zanjir_Commission_Engine::calculate( $snapshot );

		if ( empty( $rows ) ) {
			return false;
		}

		return Zanjir_Commission_Engine::save( $order_id, (int) $snapshot->id, $rows, $window_ends );
	}

	/**
	 * Backfill return_window_ends_at when commissions already exist.
	 *
	 * @param int $order_id
	 */
	private function ensure_return_window( $order_id ) {
		global $wpdb;

		$window_ends = Zanjir_Commission_Engine::get_return_window_end( $order_id );
		if ( ! $window_ends ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"UPDATE {$wpdb->prefix}zanjir_commissions
			 SET return_window_ends_at = %s, updated_at = %s
			 WHERE order_id = %d AND return_window_ends_at IS NULL AND status = 'pending'",
			$window_ends,
			current_time( 'mysql', true ),
			$order_id
		) );
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
	 * Transition all pending commissions for an order to payable (idempotent).
	 *
	 * @param int $order_id
	 * @return int Number of rows updated.
	 */
	public function transition_to_payable( $order_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );
		$rows  = self::get_pending( $order_id );
		$count = 0;

		foreach ( $rows as $row ) {
			/**
			 * Fires before a commission becomes payable.
			 *
			 * @param int $commission_id
			 * @param int $order_id
			 */
			do_action( 'zanjir_before_payable', (int) $row->id, $order_id );

			$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'     => 'payable',
					'updated_at' => $now,
				),
				array(
					'id'     => (int) $row->id,
					'status' => 'pending',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			if ( ! $updated ) {
				continue;
			}

			Zanjir_Ledger::transfer(
				(int) $row->beneficiary_id,
				'pending',
				'payable',
				(int) $row->amount,
				'commission',
				(int) $row->id
			);

			do_action( 'zanjir_commission_payable', (int) $row->id, $order_id, (int) $row->beneficiary_id, (int) $row->amount );
			++$count;
		}

		return $count;
	}

	/**
	 * Void pending and payable commissions within the return window (idempotent).
	 *
	 * Pending → void debits the pending bucket.
	 * Payable → void debits the payable bucket (clawback after window cron).
	 *
	 * @param int $order_id
	 * @return int Number of rows voided.
	 */
	public static function void_commissions( $order_id ) {
		$count  = self::void_status_bucket( $order_id, 'pending', 'pending' );
		$count += self::void_status_bucket( $order_id, 'payable', 'payable' );
		return $count;
	}

	/**
	 * Void commissions in a single status and debit the matching ledger bucket.
	 *
	 * @param int    $order_id
	 * @param string $from_status pending|payable
	 * @param string $ledger_bucket
	 * @return int
	 */
	private static function void_status_bucket( $order_id, $from_status, $ledger_bucket ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );
		$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$table} WHERE order_id = %d AND status = %s",
			$order_id,
			$from_status
		) );

		$count = 0;
		if ( ! $rows ) {
			return 0;
		}

		foreach ( $rows as $row ) {
			$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'status'     => 'void',
					'updated_at' => $now,
				),
				array(
					'id'     => (int) $row->id,
					'status' => $from_status,
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			if ( ! $updated ) {
				continue;
			}

			Zanjir_Ledger::debit(
				(int) $row->beneficiary_id,
				$ledger_bucket,
				(int) $row->amount,
				'commission',
				(int) $row->id,
				'void'
			);

			do_action( 'zanjir_commission_voided', (int) $row->id, $order_id, (int) $row->beneficiary_id, (int) $row->amount );
			++$count;
		}

		return $count;
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
