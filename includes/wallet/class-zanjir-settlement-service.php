<?php
/**
 * Monthly settlement batches — payable → paid / withdrawable.
 *
 * @package Zanjir\Wallet
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Settlement_Service {

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_settlements';
	}

	/**
	 * @return string
	 */
	private static function items_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_settlement_items';
	}

	/**
	 * @return string
	 */
	private static function commissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_commissions';
	}

	/**
	 * Create a draft settlement for payable commissions within the period.
	 *
	 * Commissions are included when their payable transition (`updated_at`) falls
	 * inside [period_start, period_end] and they are not already linked to a batch.
	 *
	 * @param string $period_start Y-m-d
	 * @param string $period_end   Y-m-d
	 * @return int|WP_Error Settlement ID.
	 */
	public static function prepare_batch( $period_start, $period_end ) {
		global $wpdb;

		$period_start = sanitize_text_field( $period_start );
		$period_end   = sanitize_text_field( $period_end );

		if ( ! $period_start || ! $period_end || $period_start > $period_end ) {
			return new WP_Error( 'invalid_period', __( 'Invalid settlement period.', 'zanjir' ) );
		}

		$rows = self::eligible_commissions( $period_start, $period_end );
		if ( empty( $rows ) ) {
			return new WP_Error( 'no_commissions', __( 'No payable commissions in this period.', 'zanjir' ) );
		}

		$total = 0;
		foreach ( $rows as $row ) {
			$total += (int) $row->amount;
		}

		$started = self::begin_transaction();
		if ( ! $started ) {
			return new WP_Error( 'db_error', __( 'Could not start settlement transaction.', 'zanjir' ) );
		}

		$now      = current_time( 'mysql', true );
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'period_start' => $period_start,
				'period_end'   => $period_end,
				'total_amount' => $total,
				'status'       => 'draft',
				'created_at'   => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			self::rollback_transaction();
			return new WP_Error( 'db_error', __( 'Could not create settlement batch.', 'zanjir' ) );
		}

		$settlement_id = (int) $wpdb->insert_id;

		foreach ( $rows as $row ) {
			$item_inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::items_table(),
				array(
					'settlement_id' => $settlement_id,
					'commission_id' => (int) $row->id,
					'amount'        => (int) $row->amount,
					'created_at'    => $now,
				),
				array( '%d', '%d', '%d', '%s' )
			);

			if ( ! $item_inserted ) {
				self::rollback_transaction();
				return new WP_Error( 'db_error', __( 'Could not lock commissions for settlement.', 'zanjir' ) );
			}
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();
			return new WP_Error( 'db_error', __( 'Could not finalize settlement batch.', 'zanjir' ) );
		}

		return $settlement_id;
	}

	/**
	 * Mark settlement as reviewed.
	 *
	 * @param int $settlement_id
	 * @return true|WP_Error
	 */
	public static function mark_reviewed( $settlement_id ) {
		return self::transition( $settlement_id, 'draft', 'reviewed' );
	}

	/**
	 * Approve settlement: move locked payable commissions to paid and ledger to withdrawable.
	 *
	 * @param int $settlement_id
	 * @param int $approver_user_id
	 * @return true|WP_Error
	 */
	public static function approve( $settlement_id, $approver_user_id ) {
		global $wpdb;

		$settlement = self::get( $settlement_id );
		if ( ! $settlement ) {
			return new WP_Error( 'not_found', __( 'Settlement not found.', 'zanjir' ) );
		}

		if ( ! in_array( $settlement->status, array( 'draft', 'reviewed' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Settlement cannot be approved from the current status.', 'zanjir' ) );
		}

		$items = self::get_items( $settlement_id );
		if ( empty( $items ) ) {
			return new WP_Error( 'empty_batch', __( 'Settlement batch has no locked commissions.', 'zanjir' ) );
		}

		$started = self::begin_transaction();
		if ( ! $started ) {
			return new WP_Error( 'db_error', __( 'Could not start settlement transaction.', 'zanjir' ) );
		}

		$now   = current_time( 'mysql', true );
		$total = 0;

		foreach ( $items as $item ) {
			$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'SELECT id, beneficiary_id, amount, status FROM ' . self::commissions_table() . ' WHERE id = %d',
				(int) $item->commission_id
			) );

			if ( ! $row || 'payable' !== $row->status ) {
				self::rollback_transaction();
				return new WP_Error(
					'commission_changed',
					__( 'A commission in this batch is no longer payable. Re-prepare the settlement.', 'zanjir' )
				);
			}

			$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				self::commissions_table(),
				array(
					'status'     => 'paid',
					'updated_at' => $now,
				),
				array(
					'id'     => (int) $row->id,
					'status' => 'payable',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);

			if ( ! $updated ) {
				self::rollback_transaction();
				return new WP_Error( 'db_error', __( 'Could not mark commission as paid.', 'zanjir' ) );
			}

			$ok = Zanjir_Ledger::transfer(
				(int) $row->beneficiary_id,
				'payable',
				'withdrawable',
				(int) $row->amount,
				'settlement',
				(int) $settlement_id
			);

			if ( ! $ok ) {
				self::rollback_transaction();
				return new WP_Error( 'ledger_error', __( 'Could not transfer commission to withdrawable balance.', 'zanjir' ) );
			}

			$total += (int) $row->amount;
		}

		$updated_settlement = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'approved',
				'total_amount' => $total,
				'approved_by'  => (int) $approver_user_id,
				'approved_at'  => $now,
			),
			array(
				'id'     => (int) $settlement_id,
				'status' => $settlement->status,
			),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $updated_settlement ) {
			self::rollback_transaction();
			return new WP_Error( 'db_error', __( 'Could not approve settlement batch.', 'zanjir' ) );
		}

		if ( ! self::commit_transaction() ) {
			self::rollback_transaction();
			return new WP_Error( 'db_error', __( 'Could not finalize settlement approval.', 'zanjir' ) );
		}

		/**
		 * Fires after a settlement batch is approved.
		 *
		 * @param int $settlement_id
		 * @param int $total
		 */
		do_action( 'zanjir_settlement_approved', (int) $settlement_id, $total );

		return true;
	}

	/**
	 * Payable commissions eligible for a settlement period (not yet batched).
	 *
	 * @param string $period_start Y-m-d
	 * @param string $period_end   Y-m-d
	 * @return array
	 */
	public static function eligible_commissions( $period_start, $period_end ) {
		global $wpdb;

		$c = self::commissions_table();
		$i = self::items_table();

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT c.id, c.beneficiary_id, c.amount
			 FROM {$c} c
			 LEFT JOIN {$i} si ON si.commission_id = c.id
			 WHERE c.status = 'payable'
			   AND si.id IS NULL
			   AND DATE(c.updated_at) >= %s
			   AND DATE(c.updated_at) <= %s
			 ORDER BY c.id ASC",
			$period_start,
			$period_end
		) );
	}

	/**
	 * Sum payable commissions within a period that are not yet batched.
	 *
	 * @param string $period_start Y-m-d
	 * @param string $period_end   Y-m-d
	 * @return int
	 */
	public static function payable_total_in_period( $period_start, $period_end ) {
		$rows = self::eligible_commissions( $period_start, $period_end );
		$total = 0;
		foreach ( $rows as $row ) {
			$total += (int) $row->amount;
		}
		return $total;
	}

	/**
	 * Settlement line items for a batch.
	 *
	 * @param int $settlement_id
	 * @return array
	 */
	public static function get_items( $settlement_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::items_table() . ' WHERE settlement_id = %d ORDER BY id ASC',
			$settlement_id
		) );
	}

	/**
	 * Count of commissions locked in a settlement batch.
	 *
	 * @param int $settlement_id
	 * @return int
	 */
	public static function item_count( $settlement_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT COUNT(*) FROM ' . self::items_table() . ' WHERE settlement_id = %d',
			$settlement_id
		) );
	}

	/**
	 * Generic status transition with guard.
	 *
	 * @param int    $settlement_id
	 * @param string $from
	 * @param string $to
	 * @return true|WP_Error
	 */
	private static function transition( $settlement_id, $from, $to ) {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array( 'status' => $to ),
			array(
				'id'     => (int) $settlement_id,
				'status' => $from,
			),
			array( '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $updated ) {
			return new WP_Error( 'invalid_status', __( 'Invalid settlement status transition.', 'zanjir' ) );
		}

		return true;
	}

	/**
	 * @param int $settlement_id
	 * @return object|null
	 */
	public static function get( $settlement_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			$settlement_id
		) );
	}

	/**
	 * @param int $limit
	 * @return array
	 */
	public static function list_recent( $limit = 20 ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
			$limit
		) );
	}

	/**
	 * Sum of amounts currently in payable status.
	 *
	 * @return int
	 */
	public static function payable_total() {
		global $wpdb;

		$total = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT COALESCE(SUM(amount),0) FROM " . self::commissions_table() . " WHERE status = 'payable'"
		);

		return (int) $total;
	}

	/**
	 * @return bool
	 */
	private static function begin_transaction() {
		global $wpdb;
		return false !== $wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return bool
	 */
	private static function commit_transaction() {
		global $wpdb;
		return false !== $wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * @return bool
	 */
	private static function rollback_transaction() {
		global $wpdb;
		return false !== $wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
