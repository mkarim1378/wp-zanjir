<?php
/**
 * Commission engine — pure calculation from snapshot.
 *
 * @package Zanjir\Commission
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Commission_Engine {

	/**
	 * Calculate commission rows from a snapshot.
	 *
	 * Pure function: snapshot in → commission rows out. No I/O.
	 *
	 * @param object $snapshot Order snapshot row.
	 * @return array<int, array{beneficiary_id: int, kind: string, tier_level: int|null, rate: int, amount: int}>
	 */
	public static function calculate( $snapshot ) {
		$rows = array();

		$base       = (int) $snapshot->base_amount;
		$tree_cap   = (int) $snapshot->tree_cap_rate;
		$staff_rate = (int) $snapshot->staff_rate;
		$chain      = json_decode( $snapshot->chain_json, true );
		$matrix     = json_decode( $snapshot->matrix_json, true );

		if ( ! is_array( $chain ) || ! is_array( $matrix ) ) {
			return $rows;
		}

		$rows = array_merge( $rows, self::calculate_tree_commissions( $base, $chain, $matrix, $tree_cap ) );

		$staff_row = self::calculate_staff_override( $base, $staff_rate, $chain, $snapshot );
		if ( $staff_row ) {
			$rows[] = $staff_row;
		}

		return $rows;
	}

	/**
	 * Calculate tree commissions per tier.
	 *
	 * @param int   $base
	 * @param array $chain
	 * @param array $matrix
	 * @param int   $tree_cap
	 * @return array
	 */
	private static function calculate_tree_commissions( $base, $chain, $matrix, $tree_cap ) {
		$rows = array();

		foreach ( $matrix as $tier ) {
			$tier_level = (int) $tier['tier'];
			$rate       = (int) $tier['rate'];
			$aff_id     = (int) $tier['aff_id'];

			if ( $aff_id <= 0 || $rate <= 0 ) {
				continue;
			}

			$amount = self::floor_divide( $base, $rate );

			$rows[] = array(
				'beneficiary_id' => $aff_id,
				'kind'           => 'tree',
				'tier_level'     => $tier_level,
				'rate'           => $rate,
				'amount'         => $amount,
			);
		}

		return $rows;
	}

	/**
	 * Calculate the staff override commission.
	 *
	 * Independent from tree cap. Recipient is the staff member who
	 * recruited the direct seller, or the default admin.
	 *
	 * @param int   $base
	 * @param int   $staff_rate
	 * @param array $chain
	 * @param object $snapshot
	 * @return array|null
	 */
	private static function calculate_staff_override( $base, $staff_rate, $chain, $snapshot ) {
		if ( $staff_rate <= 0 ) {
			return null;
		}

		$staff_id = self::resolve_staff_id( $chain, $snapshot );
		if ( ! $staff_id ) {
			return null;
		}

		$amount = self::floor_divide( $base, $staff_rate );

		return array(
			'beneficiary_id' => $staff_id,
			'kind'           => 'staff_override',
			'tier_level'     => null,
			'rate'           => $staff_rate,
			'amount'         => $amount,
		);
	}

	/**
	 * Resolve the staff member who gets the override.
	 *
	 * @param array  $chain
	 * @param object $snapshot
	 * @return int|false Staff affiliate ID or false.
	 */
	private static function resolve_staff_id( $chain, $snapshot ) {
		if ( ! empty( $chain[0] ) && ! empty( $chain[0]['staff_id'] ) ) {
			return (int) $chain[0]['staff_id'];
		}

		global $wpdb;
		$seller_id = (int) $snapshot->seller_affiliate_id;

		$tree_row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT staff_id FROM {$wpdb->prefix}zanjir_tree WHERE affiliate_id = %d",
			$seller_id
		) );

		if ( $tree_row && $tree_row->staff_id ) {
			return (int) $tree_row->staff_id;
		}

		return self::get_default_staff_id();
	}

	/**
	 * Get the default staff (admin) for override fallback.
	 *
	 * @return int|false
	 */
	private static function get_default_staff_id() {
		global $wpdb;

		$admin = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}zanjir_affiliates
			 WHERE type = 'staff' AND status = 'approved'
			 ORDER BY id ASC LIMIT 1"
		);

		return $admin ? (int) $admin : false;
	}

	/**
	 * floor(base * rate / 10000) — integer division, no rounding loss.
	 *
	 * @param int $base
	 * @param int $rate Basis-10000 rate.
	 * @return int
	 */
	private static function floor_divide( $base, $rate ) {
		return (int) floor( ( $base * $rate ) / 10000 );
	}

	/**
	 * Save calculated rows to the commissions table.
	 *
	 * @param int   $order_id
	 * @param int   $snapshot_id
	 * @param array $rows Calculated commission rows.
	 * @return bool
	 */
	public static function save( $order_id, $snapshot_id, array $rows ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );

		foreach ( $rows as $row ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				array(
					'order_id'              => $order_id,
					'snapshot_id'           => $snapshot_id,
					'beneficiary_id'        => $row['beneficiary_id'],
					'kind'                  => $row['kind'],
					'tier_level'            => $row['tier_level'],
					'rate'                  => $row['rate'],
					'amount'                => $row['amount'],
					'status'                => 'pending',
					'return_window_ends_at' => self::get_return_window_end( $order_id ),
					'created_at'            => $now,
					'updated_at'            => $now,
				),
				array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Calculate the return window end time for an order.
	 *
	 * @param int $order_id
	 * @return string|null DATETIME or null if no order found.
	 */
	private static function get_return_window_end( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$completed = $order->get_date_completed();
		if ( ! $completed ) {
			return null;
		}

		$window = (int) Zanjir_Settings::get( 'refund_window', 10 );
		$end    = clone $completed;
		$end->modify( "+{$window} days" );

		return $end->date( 'Y-m-d H:i:s' );
	}
}
