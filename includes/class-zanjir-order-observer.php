<?php
/**
 * Order observer — captures immutable snapshot at checkout.
 *
 * Commission rows are created later on order completion (see Lifecycle).
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

		$referral  = $order->get_meta( '_zanjir_referral_code' );
		$seller_id = $order->get_meta( '_zanjir_seller_id' );

		if ( ! $referral || ! $seller_id ) {
			return;
		}

		if ( self::get_snapshot( $order_id ) ) {
			return;
		}

		$base = self::calculate_base( $order );
		if ( $base <= 0 ) {
			return;
		}

		$upline = Zanjir_Tree_Service::resolve_upline_chain( (int) $seller_id, (int) Zanjir_Settings::get( 'tree_depth', 3 ) );

		$settings   = Zanjir_Settings::all();
		$matrix     = self::build_matrix_snapshot( $upline, $settings, (int) $seller_id );
		$tree_cap   = (int) $settings['tree_cap'];
		$staff_rate = (int) $settings['staff_rate'];

		$chain_payload = self::build_chain_payload( (int) $seller_id, $upline );

		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'order_id'            => $order_id,
				'referral_code'       => $referral,
				'seller_affiliate_id' => (int) $seller_id,
				'base_amount'         => $base,
				'tree_cap_rate'       => $tree_cap,
				'staff_rate'          => $staff_rate,
				'matrix_json'         => wp_json_encode( $matrix ),
				'chain_json'          => wp_json_encode( $chain_payload ),
				'created_at'          => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		/**
		 * Fires after order snapshot is captured.
		 *
		 * @param int $order_id
		 * @param int $seller_id
		 * @param int $base
		 */
		do_action( 'zanjir_after_snapshot', $order_id, (int) $seller_id, $base );
	}

	/**
	 * Calculate the commission base amount.
	 *
	 * base = sum(line_totals) - referral_discount - coupon_discount
	 * Excludes tax and shipping. Uses integer Rial amounts.
	 *
	 * @param WC_Order $order
	 * @return int Base amount in smallest currency unit (Rial).
	 */
	public static function calculate_base( $order ) {
		$total = 0;

		foreach ( $order->get_items() as $item ) {
			$total += (int) round( (float) $item->get_total() );
		}

		$referral_discount = (int) $order->get_meta( '_zanjir_referral_discount' );
		$coupon_discount   = (int) round( (float) $order->get_discount_total() );

		$base = $total - $referral_discount - $coupon_discount;

		return max( 0, $base );
	}

	/**
	 * Build a stable chain payload: seller first, then upline.
	 *
	 * @param int   $seller_id
	 * @param array $upline
	 * @return array<int, array<string, mixed>>
	 */
	public static function build_chain_payload( $seller_id, array $upline ) {
		$payload = array();

		$seller_tree = Zanjir_Tree_Service::get_node( $seller_id );
		$payload[]   = array(
			'affiliate_id' => (int) $seller_id,
			'staff_id'     => $seller_tree && $seller_tree->staff_id ? (int) $seller_tree->staff_id : null,
			'depth'        => $seller_tree ? (int) $seller_tree->depth : 0,
		);

		foreach ( $upline as $node ) {
			$payload[] = array(
				'affiliate_id' => (int) $node->affiliate_id,
				'staff_id'     => ! empty( $node->staff_id ) ? (int) $node->staff_id : null,
				'depth'        => isset( $node->depth ) ? (int) $node->depth : null,
			);
		}

		return $payload;
	}

	/**
	 * Build a matrix snapshot for the seller + upline chain.
	 *
	 * @param array $upline    Upline chain (parents only).
	 * @param array $settings  Plugin settings.
	 * @param int   $seller_id Direct seller affiliate ID.
	 * @return array Matrix rows with rates for each tier.
	 */
	private static function build_matrix_snapshot( $upline, $settings, $seller_id ) {
		$participants = array( (int) $seller_id );
		foreach ( $upline as $node ) {
			$participants[] = (int) $node->affiliate_id;
		}

		$max_depth = (int) $settings['tree_depth'];
		$participants = array_slice( $participants, 0, max( 1, $max_depth ) );
		$effective    = count( $participants );

		$matrix = array();
		for ( $i = 0; $i < $effective; $i++ ) {
			$matrix[] = array(
				'tier'   => $i + 1,
				'rate'   => self::get_tier_rate( $i, $effective ),
				'aff_id' => $participants[ $i ],
			);
		}

		return $matrix;
	}

	/**
	 * Get the rate for a given tier based on chain depth.
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
		$share = (int) intdiv( $cap, max( $depth, 1 ) );
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
			'SELECT * FROM ' . self::table() . ' WHERE order_id = %d',
			$order_id
		) );
	}
}
