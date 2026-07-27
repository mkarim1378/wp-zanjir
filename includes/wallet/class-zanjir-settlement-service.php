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
	private static function commissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_commissions';
	}

	/**
	 * Create a draft settlement for all currently payable commissions.
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

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, beneficiary_id, amount FROM " . self::commissions_table() . " WHERE status = 'payable'"
		);

		$total = 0;
		if ( $rows ) {
			foreach ( $rows as $row ) {
				$total += (int) $row->amount;
			}
		}

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'period_start' => $period_start,
				'period_end'   => $period_end,
				'total_amount' => $total,
				'status'       => 'draft',
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Could not create settlement batch.', 'zanjir' ) );
		}

		return (int) $wpdb->insert_id;
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
	 * Approve settlement: move payable commissions to paid and ledger to withdrawable.
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

		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id, beneficiary_id, amount FROM " . self::commissions_table() . " WHERE status = 'payable'"
		);
		$now   = current_time( 'mysql', true );
		$total = 0;

		if ( $rows ) {
			foreach ( $rows as $row ) {
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
					continue;
				}

				$ok = Zanjir_Ledger::transfer(
					(int) $row->beneficiary_id,
					'payable',
					'withdrawable',
					(int) $row->amount,
					'settlement',
					(int) $settlement_id
				);

				if ( $ok ) {
					$total += (int) $row->amount;
				}
			}
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'approved',
				'total_amount' => $total,
				'approved_by'  => (int) $approver_user_id,
				'approved_at'  => $now,
			),
			array( 'id' => (int) $settlement_id ),
			array( '%s', '%d', '%d', '%s' ),
			array( '%d' )
		);

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
}
