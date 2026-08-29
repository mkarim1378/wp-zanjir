<?php
/**
 * Refund handler — void commissions on order refund.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Refund_Handler {

	/**
	 * Register hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'woocommerce_order_refunded', $this, 'on_order_refunded', 10, 2 );
	}

	/**
	 * Hook: handle refund event.
	 *
	 * @param int $order_id  The order ID.
	 * @param int $refund_id The refund ID.
	 */
	public function on_order_refunded( $order_id, $refund_id ) {
		unset( $refund_id );

		$snapshot = Zanjir_Order_Observer::get_snapshot( $order_id );
		if ( ! $snapshot ) {
			return;
		}

		// Claw back pending and payable commissions regardless of return window.
		Zanjir_Commission_Lifecycle::void_commissions( $order_id );
	}

	/**
	 * Get voided commissions for an order.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public static function get_voided( $order_id ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}zanjir_commissions WHERE order_id = %d AND status = 'void'",
			$order_id
		) );
	}
}
