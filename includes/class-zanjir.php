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
		$this->loader->add_action( 'init', $this, 'ensure_cron_schedules', 20 );
	}

	/**
	 * Load text domain for i18n.
	 */
	private function define_i18n() {
		$this->loader->add_filter( 'plugin_locale', $this, 'force_persian_locale', 10, 2 );
		$this->loader->add_filter( 'load_textdomain_mofile', $this, 'force_persian_mofile', 10, 2 );
		$this->loader->add_action( 'init', $this, 'load_textdomain', 0 );
	}

	/**
	 * Always use Persian for this plugin's strings.
	 *
	 * @param string $locale Current locale.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public function force_persian_locale( $locale, $domain ) {
		if ( 'zanjir' === $domain ) {
			return 'fa_IR';
		}
		return $locale;
	}

	/**
	 * Point gettext at the bundled fa_IR catalog.
	 *
	 * @param string $mofile Path WordPress would load.
	 * @param string $domain Text domain.
	 * @return string
	 */
	public function force_persian_mofile( $mofile, $domain ) {
		if ( 'zanjir' !== $domain ) {
			return $mofile;
		}

		$forced = ZANJIR_PLUGIN_DIR . 'languages/zanjir-fa_IR.mo';
		return is_readable( $forced ) ? $forced : $mofile;
	}

	/**
	 * Load the plugin text domain (always Persian).
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'zanjir', false, dirname( ZANJIR_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Register admin-facing hooks.
	 */
	private function define_admin_hooks() {
		if ( is_admin() ) {
			$this->loader->add_action( 'admin_notices', $this, 'maybe_woocommerce_missing_notice' );

			require_once ZANJIR_PLUGIN_DIR . 'admin/class-zanjir-admin-notices.php';
			new Zanjir_Admin_Notices( $this->loader );

			require_once ZANJIR_PLUGIN_DIR . 'admin/class-zanjir-admin.php';
			new Zanjir_Admin( $this->loader );

			require_once ZANJIR_PLUGIN_DIR . 'admin/class-zanjir-admin-reports.php';
			new Zanjir_Admin_Reports( $this->loader );
		}
	}

	/**
	 * Warn admins when WooCommerce is inactive.
	 */
	public function maybe_woocommerce_missing_notice() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		if ( class_exists( 'WooCommerce' ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( '' === $page || 0 !== strpos( $page, 'zanjir' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Zanjir requires WooCommerce to process orders, commissions, and withdrawals. Activate WooCommerce to use affiliate features.', 'zanjir' )
		);
	}

	/**
	 * Register public-facing hooks.
	 *
	 * Skips WooCommerce-dependent hooks when WooCommerce is not active.
	 */
	private function define_public_hooks() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-registration.php';
		new Zanjir_Registration( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-referral-code.php';
		$this->loader->add_action( 'init', 'Zanjir_Referral_Code', 'maybe_capture_referral', 5 );
		$this->loader->add_action( 'woocommerce_checkout_order_processed', 'Zanjir_Referral_Code', 'attach_to_order', 20 );
		$this->loader->add_action( 'woocommerce_store_api_checkout_order_processed', 'Zanjir_Referral_Code', 'attach_to_order', 20 );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-discount.php';
		Zanjir_Discount::register( $this->loader );

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-order-observer.php';
		$this->loader->add_action( 'woocommerce_checkout_order_processed', 'Zanjir_Order_Observer', 'capture_snapshot', 40 );
		$this->loader->add_action( 'woocommerce_store_api_checkout_order_processed', 'Zanjir_Order_Observer', 'capture_snapshot', 40 );

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

		require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-access-gate.php';
		new Zanjir_Access_Gate( $this->loader );
	}

	/**
	 * Register recurring crons after Action Scheduler is ready (WooCommerce).
	 */
	public function ensure_cron_schedules() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		Zanjir_Recruit_Service::maybe_schedule();
		Zanjir_Bonus_Service::maybe_schedule();
		Zanjir_Commission_Lifecycle::maybe_schedule_batch();
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
		self::instance()->load_textdomain();
		Zanjir_DB::maybe_upgrade();
		Zanjir_Roles::activate();
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate() {
		Zanjir_Roles::deactivate();
		wp_unschedule_hook( Zanjir_Commission_Lifecycle::CRON_HOOK );
		Zanjir_Commission_Lifecycle::clear_batch_schedule();
		Zanjir_Recruit_Service::clear_schedule();
		Zanjir_Bonus_Service::clear_schedule();
		flush_rewrite_rules();
	}
}
