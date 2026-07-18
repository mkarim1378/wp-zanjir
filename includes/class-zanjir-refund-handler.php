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
	 * @param int $order_id    The order ID.
	 * @param int $refund_id   The refund ID.
	 */
	public function on_order_refunded( $order_id, $refund_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$snapshot = Zanjir_Order_Observer::get_snapshot( $order_id );
		if ( ! $snapshot ) {
			return;
		}

		$completed = $order->get_date_completed();
		if ( ! $completed ) {
			return;
		}

		$window = (int) Zanjir_Settings::get( 'refund_window', 10 );
		$end    = clone $completed;
		$end->modify( "+{$window} days" );
		$now    = new DateTime( current_time( 'mysql', true ) );

		if ( $now <= $end ) {
			$this->void_pending( $order_id );
		}
	}

	/**
	 * Void all pending commissions for an order (all-or-nothing).
	 *
	 * @param int $order_id
	 * @return int Number of rows voided.
	 */
	private function void_pending( $order_id ) {
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
				 * Fires when a commission is voided due to refund.
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
