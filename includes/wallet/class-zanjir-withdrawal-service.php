<?php
/**
 * Affiliate withdrawal requests.
 *
 * @package Zanjir\Wallet
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Withdrawal_Service {

	const ADMIN_NONCE = 'zanjir_withdrawal_admin';
	const USER_NONCE  = 'zanjir_withdrawal_request';

	/**
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_withdrawals';
	}

	/**
	 * Register public/admin hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_post_zanjir_withdrawal_request', $this, 'handle_request' );
		$loader->add_action( 'admin_post_zanjir_withdrawal_approve', $this, 'handle_approve' );
		$loader->add_action( 'admin_post_zanjir_withdrawal_reject', $this, 'handle_reject' );
		$loader->add_action( 'admin_post_zanjir_withdrawal_paid', $this, 'handle_paid' );
	}

	/**
	 * Available withdrawable after open requests.
	 *
	 * @param int $affiliate_id
	 * @return int
	 */
	public static function available_balance( $affiliate_id ) {
		$withdrawable = Zanjir_Ledger::get_withdrawable( $affiliate_id );
		$reserved     = self::sum_open_requests( $affiliate_id );
		return max( 0, $withdrawable - $reserved );
	}

	/**
	 * Sum of requested amounts not yet approved/rejected (pre-lock).
	 *
	 * @param int $affiliate_id
	 * @return int
	 */
	public static function sum_open_requests( $affiliate_id ) {
		global $wpdb;

		$sum = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT COALESCE(SUM(amount),0) FROM ' . self::table() . " WHERE affiliate_id = %d AND status = 'requested'",
			$affiliate_id
		) );

		return (int) $sum;
	}

	/**
	 * Create a withdrawal request.
	 *
	 * @param int    $affiliate_id
	 * @param int    $amount
	 * @param string $iban
	 * @return int|WP_Error
	 */
	public static function request( $affiliate_id, $amount, $iban = '' ) {
		global $wpdb;

		$amount = (int) $amount;
		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Invalid withdrawal amount.', 'zanjir' ) );
		}

		if ( $amount > self::available_balance( $affiliate_id ) ) {
			return new WP_Error( 'insufficient_balance', __( 'Insufficient withdrawable balance.', 'zanjir' ) );
		}

		$iban = sanitize_text_field( $iban );

		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'affiliate_id' => $affiliate_id,
				'amount'       => $amount,
				'status'       => 'requested',
				'iban'         => $iban ? $iban : null,
				'requested_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'db_error', __( 'Could not create withdrawal request.', 'zanjir' ) );
		}

		$id = (int) $wpdb->insert_id;

		/**
		 * @param int $withdrawal_id
		 * @param int $affiliate_id
		 * @param int $amount
		 */
		do_action( 'zanjir_withdrawal_requested', $id, $affiliate_id, $amount );

		return $id;
	}

	/**
	 * Approve and lock funds (debit withdrawable).
	 *
	 * @param int         $withdrawal_id
	 * @param string|null $admin_note
	 * @return true|WP_Error
	 */
	public static function approve( $withdrawal_id, $admin_note = null ) {
		global $wpdb;

		$row = self::get( $withdrawal_id );
		if ( ! $row || 'requested' !== $row->status ) {
			return new WP_Error( 'invalid_status', __( 'Withdrawal cannot be approved.', 'zanjir' ) );
		}

		if ( (int) $row->amount > Zanjir_Ledger::get_withdrawable( (int) $row->affiliate_id ) ) {
			return new WP_Error( 'insufficient_balance', __( 'Insufficient withdrawable balance.', 'zanjir' ) );
		}

		$debited = Zanjir_Ledger::debit(
			(int) $row->affiliate_id,
			'withdrawable',
			(int) $row->amount,
			'withdrawal',
			(int) $withdrawal_id,
			'lock'
		);

		if ( ! $debited ) {
			return new WP_Error( 'ledger_error', __( 'Could not lock withdrawal amount.', 'zanjir' ) );
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'approved',
				'admin_note'   => $admin_note ? sanitize_text_field( $admin_note ) : $row->admin_note,
				'processed_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => (int) $withdrawal_id,
				'status' => 'requested',
			),
			array( '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $updated ) {
			Zanjir_Ledger::credit( (int) $row->affiliate_id, 'withdrawable', (int) $row->amount, 'withdrawal', (int) $withdrawal_id, 'lock_rollback' );
			return new WP_Error( 'db_error', __( 'Could not approve withdrawal.', 'zanjir' ) );
		}

		do_action( 'zanjir_withdrawal_approved', (int) $withdrawal_id );
		return true;
	}

	/**
	 * Reject a request (release lock if already approved).
	 *
	 * @param int         $withdrawal_id
	 * @param string|null $admin_note
	 * @return true|WP_Error
	 */
	public static function reject( $withdrawal_id, $admin_note = null ) {
		global $wpdb;

		$row = self::get( $withdrawal_id );
		if ( ! $row || ! in_array( $row->status, array( 'requested', 'approved' ), true ) ) {
			return new WP_Error( 'invalid_status', __( 'Withdrawal cannot be rejected.', 'zanjir' ) );
		}

		if ( 'approved' === $row->status ) {
			Zanjir_Ledger::credit(
				(int) $row->affiliate_id,
				'withdrawable',
				(int) $row->amount,
				'withdrawal',
				(int) $withdrawal_id,
				'release'
			);
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'rejected',
				'admin_note'   => $admin_note ? sanitize_text_field( $admin_note ) : $row->admin_note,
				'processed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => (int) $withdrawal_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		do_action( 'zanjir_withdrawal_rejected', (int) $withdrawal_id );
		return true;
	}

	/**
	 * Mark approved withdrawal as paid (bank transfer done).
	 *
	 * @param int $withdrawal_id
	 * @return true|WP_Error
	 */
	public static function mark_paid( $withdrawal_id ) {
		global $wpdb;

		$row = self::get( $withdrawal_id );
		if ( ! $row || 'approved' !== $row->status ) {
			return new WP_Error( 'invalid_status', __( 'Withdrawal cannot be marked paid.', 'zanjir' ) );
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'status'       => 'paid',
				'processed_at' => current_time( 'mysql', true ),
			),
			array(
				'id'     => (int) $withdrawal_id,
				'status' => 'approved',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		if ( ! $updated ) {
			return new WP_Error( 'db_error', __( 'Could not mark withdrawal as paid.', 'zanjir' ) );
		}

		do_action( 'zanjir_withdrawal_paid', (int) $withdrawal_id );
		return true;
	}

	/**
	 * @param int $withdrawal_id
	 * @return object|null
	 */
	public static function get( $withdrawal_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE id = %d',
			$withdrawal_id
		) );
	}

	/**
	 * @param string $status
	 * @param int    $limit
	 * @return array
	 */
	public static function list_by_status( $status = '', $limit = 50 ) {
		global $wpdb;

		if ( $status ) {
			return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY id DESC LIMIT %d',
				$status,
				$limit
			) );
		}

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d',
			$limit
		) );
	}

	/**
	 * @param int $affiliate_id
	 * @param int $limit
	 * @return array
	 */
	public static function list_for_affiliate( $affiliate_id, $limit = 20 ) {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'SELECT * FROM ' . self::table() . ' WHERE affiliate_id = %d ORDER BY id DESC LIMIT %d',
			$affiliate_id,
			$limit
		) );
	}

	/**
	 * Frontend/admin-post: affiliate submits withdrawal.
	 */
	public function handle_request() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}

		check_admin_referer( self::USER_NONCE );

		$affiliate = Zanjir_Registration::get_affiliate_by_user( get_current_user_id() );
		if ( ! $affiliate || 'approved' !== $affiliate->status ) {
			wp_die( esc_html__( 'Affiliate account required.', 'zanjir' ) );
		}

		$amount = isset( $_POST['amount'] ) ? absint( $_POST['amount'] ) : 0;
		$iban   = isset( $_POST['iban'] ) ? sanitize_text_field( wp_unslash( $_POST['iban'] ) ) : '';

		$result = self::request( (int) $affiliate->id, $amount, $iban );
		if ( is_wp_error( $result ) ) {
			set_transient( 'zanjir_wd_error_' . get_current_user_id(), $result->get_error_message(), 30 );
		} else {
			set_transient( 'zanjir_wd_success_' . get_current_user_id(), __( 'Withdrawal requested.', 'zanjir' ), 30 );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/**
	 * Admin approve.
	 */
	public function handle_approve() {
		$this->guard_admin();
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( self::ADMIN_NONCE . $id );
		self::approve( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-withdrawals&done=approved' ) );
		exit;
	}

	/**
	 * Admin reject.
	 */
	public function handle_reject() {
		$this->guard_admin();
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( self::ADMIN_NONCE . $id );
		self::reject( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-withdrawals&done=rejected' ) );
		exit;
	}

	/**
	 * Admin mark paid.
	 */
	public function handle_paid() {
		$this->guard_admin();
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( self::ADMIN_NONCE . $id );
		self::mark_paid( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-withdrawals&done=paid' ) );
		exit;
	}

	/**
	 * Capability guard.
	 */
	private function guard_admin() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
	}
}
