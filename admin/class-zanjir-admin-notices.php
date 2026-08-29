<?php
/**
 * Flash admin notices for Zanjir admin-post redirects.
 *
 * @package Zanjir\Admin
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Admin_Notices {

	/**
	 * Register hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_notices', array( $this, 'render_flash_notices' ) );
	}

	/**
	 * Success messages keyed by ?done= value.
	 *
	 * @return array
	 */
	private function success_messages() {
		return array(
			'prepared'  => __( 'Settlement draft batch prepared.', 'zanjir' ),
			'reviewed'  => __( 'Settlement marked as reviewed.', 'zanjir' ),
			'approved'  => __( 'Settlement approved.', 'zanjir' ),
			'created'   => __( 'Bonus plan created.', 'zanjir' ),
			'type'      => __( 'Affiliate type updated.', 'zanjir' ),
			'discount'  => __( 'Referral discount updated.', 'zanjir' ),
		);
	}

	/**
	 * Error messages keyed by ?error= code.
	 *
	 * @return array
	 */
	private function error_messages() {
		return array(
			'invalid_period'    => __( 'Invalid settlement period.', 'zanjir' ),
			'no_commissions'    => __( 'No payable commissions in this period.', 'zanjir' ),
			'db_error'          => __( 'Database error. Please try again.', 'zanjir' ),
			'not_found'         => __( 'Settlement not found.', 'zanjir' ),
			'invalid_status'    => __( 'Action not allowed for the current status.', 'zanjir' ),
			'empty_batch'       => __( 'Settlement batch has no locked commissions.', 'zanjir' ),
			'ledger_error'      => __( 'Could not transfer commission to withdrawable balance.', 'zanjir' ),
			'commission_changed' => __( 'A commission in this batch is no longer payable. Re-prepare the settlement.', 'zanjir' ),
			'invalid_title'     => __( 'Bonus plan title is required.', 'zanjir' ),
			'invalid_metric'    => __( 'Invalid bonus metric.', 'zanjir' ),
			'invalid_reward'    => __( 'Invalid reward type.', 'zanjir' ),
			'no_referral_code'  => __( 'Affiliate has no referral code.', 'zanjir' ),
		);
	}

	/**
	 * Show success/error notices on Zanjir admin screens.
	 */
	public function render_flash_notices() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $page || 0 !== strpos( $page, 'zanjir' ) ) {
			return;
		}

		if ( isset( $_GET['status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$status = sanitize_key( wp_unslash( $_GET['status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( 'approved' === $status ) {
				$this->output_notice( 'success', __( 'Affiliate approved.', 'zanjir' ) );
			} elseif ( 'rejected' === $status ) {
				$this->output_notice( 'success', __( 'Affiliate rejected.', 'zanjir' ) );
			}
		}

		if ( isset( $_GET['done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$key     = sanitize_key( wp_unslash( $_GET['done'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$success = $this->success_messages();
			if ( isset( $success[ $key ] ) ) {
				$this->output_notice( 'success', $success[ $key ] );
			}
		}

		if ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$code    = sanitize_key( wp_unslash( $_GET['error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$errors  = $this->error_messages();
			$message = isset( $errors[ $code ] ) ? $errors[ $code ] : __( 'An error occurred.', 'zanjir' );
			$this->output_notice( 'error', $message );
		}
	}

	/**
	 * Print a single admin notice.
	 *
	 * @param string $type    success|error
	 * @param string $message Notice text.
	 */
	private function output_notice( $type, $message ) {
		$class = 'success' === $type ? 'notice-success' : 'notice-error';
		printf(
			'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $class ),
			esc_html( $message )
		);
	}
}
