<?php
/**
 * Matrix engine with strict invariant validation.
 *
 * @package Zanjir\Commission
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Matrix {

	/**
	 * Get the default matrix.
	 *
	 * @return array<int, array{depth: int, rates: array<int, int>}>
	 */
	public static function defaults() {
		return array(
			array( 'depth' => 1, 'rates' => array( 2000 ), 'tree_cap' => 2000 ),
			array( 'depth' => 2, 'rates' => array( 1250, 750 ), 'tree_cap' => 2000 ),
			array( 'depth' => 3, 'rates' => array( 1000, 750, 250 ), 'tree_cap' => 2000 ),
		);
	}

	/**
	 * Get the matrix from settings.
	 *
	 * @return array
	 */
	public static function load() {
		$all = Zanjir_Settings::all();
		if ( isset( $all['matrix'] ) && is_array( $all['matrix'] ) ) {
			return $all['matrix'];
		}
		return self::defaults();
	}

	/**
	 * Save the matrix into settings after validation.
	 *
	 * @param array $rows Matrix rows.
	 * @return true|WP_Error
	 */
	public static function save( array $rows ) {
		$error = self::validate( $rows );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		Zanjir_Settings::update( array( 'matrix' => $rows ) );
		return true;
	}

	/**
	 * Validate the full matrix.
	 *
	 * Rules:
	 * - At least one row.
	 * - Each row's rates sum must equal tree_cap.
	 * - Direct seller (tier 1) must have the highest rate in each row.
	 * - Row depth must match the number of rates.
	 *
	 * @param array $rows
	 * @return true|WP_Error
	 */
	public static function validate( array $rows ) {
		if ( empty( $rows ) ) {
			return new WP_Error( 'empty_matrix', __( 'Matrix must have at least one row.', 'zanjir' ) );
		}

		$global_cap = (int) Zanjir_Settings::get( 'tree_cap', 2000 );

		foreach ( $rows as $i => $row ) {
			if ( ! isset( $row['depth'], $row['rates'] ) || ! is_array( $row['rates'] ) ) {
				return new WP_Error( 'invalid_row', sprintf( __( 'Row %d is invalid.', 'zanjir' ), $i + 1 ) );
			}

			$depth   = (int) $row['depth'];
			$rates   = array_map( 'intval', $row['rates'] );
			$row_cap = isset( $row['tree_cap'] ) ? (int) $row['tree_cap'] : $global_cap;

			if ( count( $rates ) !== $depth ) {
				return new WP_Error(
					'depth_mismatch',
					sprintf( __( 'Row %d: depth is %d but %d rates provided.', 'zanjir' ), $i + 1, $depth, count( $rates ) )
				);
			}

			$sum = array_sum( $rates );
			if ( $sum !== $row_cap ) {
				return new WP_Error(
					'sum_mismatch',
					sprintf(
						/* translators: 1: row number, 2: sum, 3: tree cap */
						__( 'Row %d: rates sum to %d but tree cap is %d.', 'zanjir' ),
						$i + 1,
						$sum,
						$row_cap
					)
				);
			}

			$seller_rate = $rates[0];
			$others      = array_slice( $rates, 1 );
			if ( ! empty( $others ) && $seller_rate <= max( $others ) ) {
				return new WP_Error(
					'seller_not_highest',
					sprintf( __( 'Row %d: direct seller must have the highest rate.', 'zanjir' ), $i + 1 )
				);
			}
		}

		return true;
	}

	/**
	 * Select the appropriate row for a given chain depth.
	 *
	 * @param int $chain_depth Number of upline members available.
	 * @return array|null Selected row or null if no match.
	 */
	public static function select_row( $chain_depth ) {
		$matrix = self::load();
		$depth  = max( 1, (int) $chain_depth );

		foreach ( $matrix as $row ) {
			if ( (int) $row['depth'] === $depth ) {
				return $row;
			}
		}

		$closest = null;
		foreach ( $matrix as $row ) {
			if ( (int) $row['depth'] <= $depth ) {
				$closest = $row;
			}
		}

		return $closest;
	}

	/**
	 * Get rates for a specific depth.
	 *
	 * @param int $chain_depth
	 * @return array<int, int> Rates in basis-10000.
	 */
	public static function get_rates( $chain_depth ) {
		$row = self::select_row( $chain_depth );
		return $row ? array_map( 'intval', $row['rates'] ) : array();
	}
}
