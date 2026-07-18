<?php
/**
 * Order observer — captures immutable snapshot at checkout.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Order_Observer {

	/**
	 * Get the snapshots table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_order_snapshots';
	}

	/**
	 * Hook: capture snapshot when order is processed at checkout.
	 *
	 * @param int $order_id Order ID (passed by woocommerce_checkout_order_processed).
	 */
	public static function capture_snapshot( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$referral  = get_post_meta( $order_id, '_zanjir_referral_code', true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seller_id = get_post_meta( $order_id, '_zanjir_seller_id', true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $referral || ! $seller_id ) {
			return;
		}

		$base = self::calculate_base( $order );
		if ( $base <= 0 ) {
			return;
		}

		$chain = Zanjir_Tree_Service::resolve_upline_chain( (int) $seller_id, (int) Zanjir_Settings::get( 'tree_depth', 3 ) );

		$settings   = Zanjir_Settings::all();
		$matrix     = self::build_matrix_snapshot( $chain, $settings );
		$tree_cap   = (int) $settings['tree_cap'];
		$staff_rate = (int) $settings['staff_rate'];

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'order_id'              => $order_id,
				'referral_code'         => $referral,
				'seller_affiliate_id'   => (int) $seller_id,
				'base_amount'           => $base,
				'tree_cap_rate'         => $tree_cap,
				'staff_rate'            => $staff_rate,
				'matrix_json'           => wp_json_encode( $matrix ),
				'chain_json'            => wp_json_encode( $chain ),
				'created_at'            => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		$snapshot_id = $wpdb->insert_id;

		$commissions = Zanjir_Commission_Engine::calculate( (object) array(
			'base_amount'         => $base,
			'tree_cap_rate'       => $tree_cap,
			'staff_rate'          => $staff_rate,
			'chain_json'          => wp_json_encode( $chain ),
			'matrix_json'         => wp_json_encode( $matrix ),
			'seller_affiliate_id' => (int) $seller_id,
		) );

		if ( ! empty( $commissions ) ) {
			Zanjir_Commission_Engine::save( $order_id, $snapshot_id, $commissions );
		}

		/**
		 * Fires after order snapshot is captured.
		 *
		 * @param int   $order_id
		 * @param int   $seller_id
		 * @param float $base
		 */
		do_action( 'zanjir_after_snapshot', $order_id, $seller_id, $base );
	}

	/**
	 * Calculate the commission base amount.
	 *
	 * base = sum(line_totals) - referral_discount - coupon_discount
	 * Excludes tax and shipping.
	 *
	 * @param WC_Order $order
	 * @return int Base amount in smallest currency unit (Rial).
	 */
	public static function calculate_base( $order ) {
		$total = 0;

		foreach ( $order->get_items() as $item ) {
			$total += (float) $item->get_total();
		}

		$referral_discount = (float) get_post_meta( $order->get_id(), '_zanjir_referral_discount', true ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$coupon_discount   = (float) $order->get_discount_total();

		$base = $total - $referral_discount - $coupon_discount;

		return max( 0, (int) round( $base ) );
	}

	/**
	 * Build a matrix snapshot for the current chain.
	 *
	 * @param array $chain    Upline chain.
	 * @param array $settings Plugin settings.
	 * @return array Matrix rows with rates for each tier.
	 */
	private static function build_matrix_snapshot( $chain, $settings ) {
		$depth      = count( $chain );
		$max_depth  = (int) $settings['tree_depth'];
		$effective  = min( $depth, $max_depth );

		$matrix = array();
		for ( $i = 0; $i < $effective; $i++ ) {
			$matrix[] = array(
				'tier'     => $i + 1,
				'rate'     => self::get_tier_rate( $i, $effective ),
				'aff_id'   => isset( $chain[ $i ] ) ? (int) $chain[ $i ]->affiliate_id : 0,
			);
		}

		return $matrix;
	}

	/**
	 * Get the rate for a given tier based on chain depth.
	 *
	 * Uses the settings matrix if defined, otherwise falls back to
	 * the default distribution.
	 *
	 * @param int $tier_index 0-based tier position (0 = direct seller).
	 * @param int $depth      Total effective depth.
	 * @return int Rate in basis-10000.
	 */
	private static function get_tier_rate( $tier_index, $depth ) {
		$rates = Zanjir_Matrix::get_rates( $depth );
		if ( isset( $rates[ $tier_index ] ) ) {
			return (int) $rates[ $tier_index ];
		}

		$cap   = (int) Zanjir_Settings::get( 'tree_cap', 2000 );
		$share = (int) floor( $cap / max( $depth, 1 ) );
		return $share;
	}

	/**
	 * Get snapshot for an order.
	 *
	 * @param int $order_id
	 * @return object|null
	 */
	public static function get_snapshot( $order_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM " . self::table() . " WHERE order_id = %d",
			$order_id
		) );
	}
}
