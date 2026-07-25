<?php
/**
 * Dual-entry ledger — source of truth for affiliate balances.
 *
 * @package Zanjir\Wallet
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Ledger {

	/**
	 * Get the ledger table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_wallet_ledger';
	}

	/**
	 * Record a credit entry.
	 *
	 * @param int    $affiliate_id
	 * @param string $bucket pending|payable|withdrawable
	 * @param int    $amount
	 * @param string $ref_type commission|settlement|withdrawal|adjustment
	 * @param int|null $ref_id
	 * @param string|null $note
	 * @return int|false Insert ID or false.
	 */
	public static function credit( $affiliate_id, $bucket, $amount, $ref_type, $ref_id = null, $note = null ) {
		return self::insert( $affiliate_id, 'credit', $bucket, $amount, $ref_type, $ref_id, $note );
	}

	/**
	 * Record a debit entry.
	 *
	 * @param int    $affiliate_id
	 * @param string $bucket pending|payable|withdrawable
	 * @param int    $amount
	 * @param string $ref_type commission|settlement|withdrawal|adjustment
	 * @param int|null $ref_id
	 * @param string|null $note
	 * @return int|false Insert ID or false.
	 */
	public static function debit( $affiliate_id, $bucket, $amount, $ref_type, $ref_id = null, $note = null ) {
		return self::insert( $affiliate_id, 'debit', $bucket, $amount, $ref_type, $ref_id, $note );
	}

	/**
	 * Insert a ledger entry.
	 *
	 * @param int    $affiliate_id
	 * @param string $entry_type credit|debit
	 * @param string $bucket
	 * @param int    $amount
	 * @param string $ref_type
	 * @param int|null $ref_id
	 * @param string|null $note
	 * @return int|false
	 */
	private static function insert( $affiliate_id, $entry_type, $bucket, $amount, $ref_type, $ref_id, $note ) {
		global $wpdb;

		$balance = self::get_balance( $affiliate_id, $bucket );

		if ( 'credit' === $entry_type ) {
			$balance += $amount;
		} else {
			$balance -= $amount;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'affiliate_id'  => $affiliate_id,
				'entry_type'    => $entry_type,
				'bucket'        => $bucket,
				'amount'        => $amount,
				'balance_after' => $balance,
				'ref_type'      => $ref_type,
				'ref_id'        => $ref_id,
				'note'          => $note,
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Get the balance for a specific bucket.
	 *
	 * @param int    $affiliate_id
	 * @param string $bucket
	 * @return int
	 */
	public static function get_balance( $affiliate_id, $bucket ) {
		global $wpdb;

		$balance = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT balance_after FROM " . self::table() . "
			 WHERE affiliate_id = %d AND bucket = %s
			 ORDER BY id DESC LIMIT 1",
			$affiliate_id,
			$bucket
		) );

		return false !== $balance ? (int) $balance : 0;
	}

	/**
	 * Get total withdrawable balance.
	 *
	 * @param int $affiliate_id
	 * @return int
	 */
	public static function get_withdrawable( $affiliate_id ) {
		return self::get_balance( $affiliate_id, 'withdrawable' );
	}

	/**
	 * Get all ledger entries for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @param int $limit
	 * @param int $offset
	 * @return array
	 */
	public static function get_entries( $affiliate_id, $limit = 50, $offset = 0 ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM " . self::table() . "
			 WHERE affiliate_id = %d
			 ORDER BY id DESC
			 LIMIT %d OFFSET %d",
			$affiliate_id,
			$limit,
			$offset
		) );
	}

	/**
	 * Transfer balance between buckets.
	 *
	 * Creates a debit from source and credit to destination.
	 *
	 * @param int    $affiliate_id
	 * @param string $from_bucket
	 * @param string $to_bucket
	 * @param int    $amount
	 * @param string $ref_type
	 * @param int|null $ref_id
	 * @param string|null $note
	 * @return bool
	 */
	public static function transfer( $affiliate_id, $from_bucket, $to_bucket, $amount, $ref_type, $ref_id = null, $note = null ) {
		$debit_id  = self::debit( $affiliate_id, $from_bucket, $amount, $ref_type, $ref_id, $note );
		$credit_id = self::credit( $affiliate_id, $to_bucket, $amount, $ref_type, $ref_id, $note );

		return $debit_id && $credit_id;
	}
}
