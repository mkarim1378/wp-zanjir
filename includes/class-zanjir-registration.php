<?php
/**
 * Affiliate registration form and manual approval flow.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Registration {

	/**
	 * Registration form nonce field name.
	 */
	const NONCE_FIELD = 'zanjir_nonce';

	/**
	 * Registration form action nonce.
	 */
	const NONCE_ACTION = 'zanjir_register';

	/**
	 * Admin approval nonce.
	 */
	const ADMIN_NONCE = 'zanjir_admin_action';

	/**
	 * Register hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'init', $this, 'handle_registration' );
		$loader->add_action( 'admin_post_zanjir_approve_affiliate', $this, 'handle_approve' );
		$loader->add_action( 'admin_post_zanjir_reject_affiliate', $this, 'handle_reject' );
	}

	/**
	 * Handle registration form submission on frontend.
	 */
	public function handle_registration() {
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id      = get_current_user_id();
		$national_id  = isset( $_POST['zanjir_national_id'] ) ? sanitize_text_field( wp_unslash( $_POST['zanjir_national_id'] ) ) : '';
		$referral     = isset( $_POST['zanjir_referral_code'] ) ? sanitize_text_field( wp_unslash( $_POST['zanjir_referral_code'] ) ) : '';

		$result = $this->register( $user_id, $national_id, $referral );

		if ( is_wp_error( $result ) ) {
			set_transient( 'zanjir_reg_error', $result->get_error_message(), 30 );
		} else {
			set_transient( 'zanjir_reg_success', __( 'Registration submitted. Waiting for admin approval.', 'zanjir' ), 30 );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
		exit;
	}

	/**
	 * Register a new affiliate.
	 *
	 * @param int    $user_id    WordPress user ID.
	 * @param string $national_id Raw national ID.
	 * @param string $referral_code Referral code (optional, for parent link).
	 * @return true|WP_Error
	 */
	public function register( $user_id, $national_id, $referral_code = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'zanjir_affiliates';

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists ) {
			return new WP_Error( 'already_registered', __( 'You are already registered as an affiliate.', 'zanjir' ) );
		}

		$nid = Zanjir_National_Id_Validator::process( $national_id );
		if ( ! $nid || ! $nid['valid'] ) {
			return new WP_Error( 'invalid_national_id', __( 'Invalid national ID.', 'zanjir' ) );
		}

		$dup = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE national_id_hash = %s", $nid['hash'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $dup ) {
			return new WP_Error( 'duplicate_national_id', __( 'This national ID is already registered.', 'zanjir' ) );
		}

		$now    = current_time( 'mysql', true );
		$insert = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array(
				'user_id'          => $user_id,
				'type'             => 'affiliate',
				'status'           => 'pending',
				'national_id_hash' => $nid['hash'],
				'national_id_enc'  => $nid['encrypted'] ? $nid['encrypted'] : null,
				'recruit_enabled'  => 0,
				'annual_sales'     => 0,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( ! $insert ) {
			return new WP_Error( 'db_error', __( 'Registration failed. Please try again.', 'zanjir' ) );
		}

		$affiliate_id = $wpdb->insert_id;

		if ( $referral_code ) {
			$this->link_parent( $affiliate_id, $referral_code );
		}

		/**
		 * Fires after affiliate registration.
		 *
		 * @param int    $affiliate_id New affiliate row ID.
		 * @param int    $user_id      WordPress user ID.
		 * @param string $national_id  Hashed national ID.
		 */
		do_action( 'zanjir_after_registration', $affiliate_id, $user_id, $nid['hash'] );

		return true;
	}

	/**
	 * Link parent via referral code.
	 *
	 * Stores the parent affiliate ID for tree insertion on approval.
	 *
	 * @param int    $affiliate_id
	 * @param string $referral_code
	 */
	private function link_parent( $affiliate_id, $referral_code ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'zanjir_referral_codes';
		$parent = $wpdb->get_row( $wpdb->prepare( "SELECT affiliate_id FROM {$table} WHERE code = %s AND active = 1", $referral_code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( $parent ) {
			update_option( 'zanjir_pending_parent_' . $affiliate_id, (int) $parent->affiliate_id );
		}
	}

	/**
	 * Handle admin approve action.
	 */
	public function handle_approve() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}

		if ( ! isset( $_GET['affiliate_id'], $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'zanjir' ) );
		}

		$affiliate_id = absint( $_GET['affiliate_id'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ADMIN_NONCE . $affiliate_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'zanjir' ) );
		}

		$this->set_status( $affiliate_id, 'approved' );
		Zanjir_Roles::assign_affiliate( $this->get_user_id( $affiliate_id ) );
		Zanjir_Referral_Code::generate( $affiliate_id );

		$parent_id = get_option( 'zanjir_pending_parent_' . $affiliate_id, null );
		if ( $parent_id ) {
			Zanjir_Tree_Service::insert( $affiliate_id, (int) $parent_id );
			delete_option( 'zanjir_pending_parent_' . $affiliate_id );
		} else {
			Zanjir_Tree_Service::insert( $affiliate_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zanjir&status=approved' ) );
		exit;
	}

	/**
	 * Handle admin reject action.
	 */
	public function handle_reject() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}

		if ( ! isset( $_GET['affiliate_id'], $_GET['_wpnonce'] ) ) {
			wp_die( esc_html__( 'Invalid request.', 'zanjir' ) );
		}

		$affiliate_id = absint( $_GET['affiliate_id'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::ADMIN_NONCE . $affiliate_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'zanjir' ) );
		}

		$this->set_status( $affiliate_id, 'rejected' );
		delete_option( 'zanjir_pending_parent_' . $affiliate_id );
		Zanjir_Tree_Service::remove( $affiliate_id );

		wp_safe_redirect( admin_url( 'admin.php?page=zanjir&status=rejected' ) );
		exit;
	}

	/**
	 * Update affiliate status.
	 *
	 * @param int    $affiliate_id
	 * @param string $status pending|approved|rejected|suspended
	 * @return bool
	 */
	public function set_status( $affiliate_id, $status ) {
		global $wpdb;

		$valid = array( 'pending', 'approved', 'rejected', 'suspended' );
		if ( ! in_array( $status, $valid, true ) ) {
			return false;
		}

		$now = current_time( 'mysql', true );
		$data = array(
			'status'     => $status,
			'updated_at' => $now,
		);

		if ( 'approved' === $status ) {
			$data['approved_at'] = $now;
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . 'zanjir_affiliates',
			$data,
			array( 'id' => $affiliate_id ),
			array_fill( 0, count( $data ), '%s' ),
			array( '%d' )
		);

		/**
		 * Fires when affiliate status changes.
		 *
		 * @param int    $affiliate_id
		 * @param string $old_status
		 * @param string $new_status
		 */
		do_action( 'zanjir_affiliate_status_changed', $affiliate_id, '', $status );

		return true;
	}

	/**
	 * Get affiliate row by user ID.
	 *
	 * @param int $user_id
	 * @return object|null
	 */
	public static function get_affiliate_by_user( $user_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zanjir_affiliates WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get affiliate row by ID.
	 *
	 * @param int $affiliate_id
	 * @return object|null
	 */
	public static function get_affiliate( $affiliate_id ) {
		global $wpdb;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}zanjir_affiliates WHERE id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Get the WordPress user ID for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return int
	 */
	private function get_user_id( $affiliate_id ) {
		$row = self::get_affiliate( $affiliate_id );
		return $row ? (int) $row->user_id : 0;
	}
}
