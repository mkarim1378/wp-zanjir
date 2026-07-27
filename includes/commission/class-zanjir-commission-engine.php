<?php
/**
 * Commission engine — calculation from snapshot + persistence.
 *
 * @package Zanjir\Commission
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Commission_Engine {

	/**
	 * Calculate commission rows from a snapshot.
	 *
	 * Staff resolution may hit the DB when staff_id is not embedded in chain_json.
	 *
	 * @param object $snapshot Order snapshot row.
	 * @return array<int, array{beneficiary_id: int, kind: string, tier_level: int|null, rate: int, amount: int}>
	 */
	public static function calculate( $snapshot ) {
		$rows = array();

		$base       = (int) $snapshot->base_amount;
		$staff_rate = (int) $snapshot->staff_rate;
		$chain      = json_decode( $snapshot->chain_json, true );
		$matrix     = json_decode( $snapshot->matrix_json, true );

		if ( ! is_array( $chain ) || ! is_array( $matrix ) ) {
			return $rows;
		}

		/**
		 * Filter calculated commission rows before persistence.
		 *
		 * @param array  $rows
		 * @param object $snapshot
		 */
		$rows = array_merge( $rows, self::calculate_tree_commissions( $base, $matrix ) );

		$staff_row = self::calculate_staff_override( $base, $staff_rate, $chain, $snapshot );
		if ( $staff_row ) {
			$rows[] = $staff_row;
		}

		return apply_filters( 'zanjir_commission_result', $rows, $snapshot );
	}

	/**
	 * Calculate tree commissions per tier.
	 *
	 * @param int   $base
	 * @param array $matrix
	 * @return array
	 */
	private static function calculate_tree_commissions( $base, $matrix ) {
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
	 * @param int    $base
	 * @param int    $staff_rate
	 * @param array  $chain
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
	 * @return int|false
	 */
	private static function resolve_staff_id( $chain, $snapshot ) {
		if ( ! empty( $chain[0]['staff_id'] ) ) {
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
	 * Get the default staff affiliate for override fallback.
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
	 * floor(base * rate / 10000) using integer math.
	 *
	 * @param int $base
	 * @param int $rate Basis-10000 rate.
	 * @return int
	 */
	private static function floor_divide( $base, $rate ) {
		return (int) intdiv( (int) $base * (int) $rate, 10000 );
	}

	/**
	 * Whether commissions already exist for an order.
	 *
	 * @param int $order_id
	 * @return bool
	 */
	public static function has_commissions( $order_id ) {
		global $wpdb;

		$found = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT id FROM {$wpdb->prefix}zanjir_commissions WHERE order_id = %d LIMIT 1",
			$order_id
		) );

		return ! empty( $found );
	}

	/**
	 * Save calculated rows to the commissions table and credit pending ledger.
	 *
	 * @param int         $order_id
	 * @param int         $snapshot_id
	 * @param array       $rows
	 * @param string|null $window_ends_at MySQL DATETIME UTC.
	 * @return bool
	 */
	public static function save( $order_id, $snapshot_id, array $rows, $window_ends_at = null ) {
		global $wpdb;

		if ( empty( $rows ) ) {
			return false;
		}

		$table = $wpdb->prefix . 'zanjir_commissions';
		$now   = current_time( 'mysql', true );

		if ( null === $window_ends_at ) {
			$window_ends_at = self::get_return_window_end( $order_id );
		}

		foreach ( $rows as $row ) {
			$data = array(
				'order_id'              => $order_id,
				'snapshot_id'           => $snapshot_id,
				'beneficiary_id'        => $row['beneficiary_id'],
				'kind'                  => $row['kind'],
				'tier_level'            => $row['tier_level'],
				'rate'                  => $row['rate'],
				'amount'                => $row['amount'],
				'status'                => 'pending',
				'return_window_ends_at' => $window_ends_at,
				'created_at'            => $now,
				'updated_at'            => $now,
			);

			$formats = array( '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );
			if ( null === $row['tier_level'] ) {
				$data['tier_level'] = null;
			}

			$wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$commission_id = $wpdb->insert_id;
			if ( $commission_id && (int) $row['amount'] > 0 ) {
				Zanjir_Ledger::credit( (int) $row['beneficiary_id'], 'pending', (int) $row['amount'], 'commission', $commission_id );
			}
		}

		return true;
	}

	/**
	 * Calculate the return window end time for an order.
	 *
	 * @param int $order_id
	 * @return string|null DATETIME or null if order not completed.
	 */
	public static function get_return_window_end( $order_id ) {
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
