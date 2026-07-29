<?php
/**
 * Main plugin class.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir {

	/**
	 * @var Zanjir_Loader
	 */
	private $loader;

	/**
	 * @var Zanjir
	 */
	private static $instance;

	/**
	 * Get singleton instance.
	 *
	 * @return Zanjir
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->loader = new Zanjir_Loader();
		$this->define_i18n();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load text domain for i18n.
	 */
	private function define_i18n() {
		$this->loader->add_action( 'plugins_loaded', $this, 'load_textdomain' );
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'zanjir', false, dirname( ZANJIR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register admin-facing hooks.
	 */
	private function define_admin_hooks() {
		if ( is_admin() ) {
			require_once ZANJIR_PLUGIN_DIR . 'admin/class-zanjir-admin.php';
			new Zanjir_Admin( $this->loader );

			require_once ZANJIR_PLUGIN_DIR . 'admin/class-zanjir-admin-reports.php';
			new Zanjir_Admin_Reports( $this->loader );
		}
	}

	/**
	 * Register public-facing hooks.
	 */
	private function define_public_hooks() {
		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-registration.php';
		new Zanjir_Registration( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-referral-code.php';
		$this->loader->add_action( 'init', 'Zanjir_Referral_Code', 'maybe_capture_referral', 5 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', 'Zanjir_Referral_Code', 'attach_to_order', 20 );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-discount.php';
		Zanjir_Discount::register( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-order-observer.php';
		$this->loader->add_action( 'woocommerce_checkout_order_processed', 'Zanjir_Order_Observer', 'capture_snapshot', 40 );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-commission-lifecycle.php';
		new Zanjir_Commission_Lifecycle( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-refund-handler.php';
		new Zanjir_Refund_Handler( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/wallet/class-zanjir-settlement-service.php';
		require_once ZANJIR_PLUGIN_DIR . 'includes/wallet/class-zanjir-withdrawal-service.php';
		new Zanjir_Withdrawal_Service( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/fraud/class-zanjir-fraud-guard.php';
		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-recruit-service.php';
		new Zanjir_Recruit_Service( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/bonus/class-zanjir-bonus-service.php';
		new Zanjir_Bonus_Service( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'public/class-zanjir-public.php';
		new Zanjir_Public( $this->loader );

		Zanjir_Recruit_Service::maybe_schedule();
		Zanjir_Bonus_Service::maybe_schedule();
	}

	/**
	 * Run the loader.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the loader instance.
	 *
	 * @return Zanjir_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Plugin activation callback.
	 */
	public static function activate() {
		Zanjir_DB::maybe_upgrade();
		Zanjir_Roles::activate();
		Zanjir_Recruit_Service::maybe_schedule();
		Zanjir_Bonus_Service::maybe_schedule();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate() {
		Zanjir_Roles::deactivate();
		wp_unschedule_hook( Zanjir_Commission_Lifecycle::CRON_HOOK );
		Zanjir_Recruit_Service::clear_schedule();
		Zanjir_Bonus_Service::clear_schedule();
		flush_rewrite_rules();
	}
}
