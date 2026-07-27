<?php
/**
 * Anti-fraud checks and fraud log writer.
 *
 * @package Zanjir\Fraud
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Fraud_Guard {

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_fraud_logs';
	}

	/**
	 * Run checkout/pre-commission fraud checks for an order.
	 *
	 * @param int $order_id
	 * @param int $seller_id
	 * @return true|WP_Error True if allowed; WP_Error if blocked.
	 */
	public static function precheck_order( $order_id, $seller_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return true;
		}

		$rules = apply_filters(
			'zanjir_fraud_rules',
			array(
				'self_buy'   => true,
				'own_chain'  => true,
				'dup_ip_log' => true,
			),
			$order_id,
			$seller_id
		);

		$buyer_id = (int) $order->get_user_id();
		$seller   = Zanjir_Registration::get_affiliate( $seller_id );

		if ( ! empty( $rules['self_buy'] ) && $seller && $buyer_id && (int) $seller->user_id === $buyer_id ) {
			self::log( 'self_buy', 'critical', $order_id, $seller_id, array( 'buyer_id' => $buyer_id ) );
			return new WP_Error( 'self_buy', __( 'Self-purchase referrals are not allowed.', 'zanjir' ) );
		}

		if ( ! empty( $rules['own_chain'] ) && $buyer_id ) {
			$buyer_aff = Zanjir_Registration::get_affiliate_by_user( $buyer_id );
			if ( $buyer_aff && 'approved' === $buyer_aff->status ) {
				if ( Zanjir_Tree_Service::is_descendant( (int) $seller_id, (int) $buyer_aff->id )
					|| (int) $buyer_aff->id === (int) $seller_id ) {
					self::log(
						'own_chain',
						'critical',
						$order_id,
						$seller_id,
						array( 'buyer_affiliate_id' => (int) $buyer_aff->id )
					);
					return new WP_Error( 'own_chain', __( 'Purchases inside your own referral chain are not allowed.', 'zanjir' ) );
				}
			}
		}

		if ( ! empty( $rules['dup_ip_log'] ) ) {
			$ip = self::request_ip();
			if ( $ip ) {
				self::log( 'ip_seen', 'info', $order_id, $seller_id, array(), $ip );
			}
		}

		return true;
	}

	/**
	 * Persist a fraud log row.
	 *
	 * @param string     $event_type
	 * @param string     $severity info|warning|critical
	 * @param int|null   $order_id
	 * @param int|null   $affiliate_id
	 * @param array|null $meta
	 * @param string     $ip
	 * @return int|false
	 */
	public static function log( $event_type, $severity = 'warning', $order_id = null, $affiliate_id = null, $meta = null, $ip = '' ) {
		global $wpdb;

		$ip_hash = $ip ? hash( 'sha256', $ip . wp_salt( 'auth' ) ) : null;

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'event_type'   => sanitize_key( $event_type ),
				'severity'    => $severity,
				'order_id'     => $order_id,
				'affiliate_id' => $affiliate_id,
				'ip_hash'      => $ip_hash,
				'meta_json'    => $meta ? wp_json_encode( $meta ) : null,
				'reviewed'     => 0,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s' )
		);

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * @return string
	 */
	private static function request_ip() {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}
		return '';
	}

	/**
	 * Unreviewed fraud events.
	 *
	 * @param int $limit
	 * @return array
	 */
	public static function list_unreviewed( $limit = 50 ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE reviewed = 0 ORDER BY id DESC LIMIT %d',
			$limit
		) );
	}

	/**
	 * Mark a fraud log reviewed.
	 *
	 * @param int $log_id
	 * @return bool
	 */
	public static function mark_reviewed( $log_id ) {
		global $wpdb;

		return (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array( 'reviewed' => 1 ),
			array( 'id' => (int) $log_id ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
