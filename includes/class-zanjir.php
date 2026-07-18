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
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Register admin-facing hooks.
	 */
	private function define_admin_hooks() {
		// Placeholder for admin hooks (Phase 3+).
	}

	/**
	 * Register public-facing hooks.
	 */
	private function define_public_hooks() {
		// Placeholder for public hooks (Phase 8+).
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
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation callback.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

/**
 * Initialize the plugin.
 *
 * @return Zanjir
 */
function zanjir() {
	$plugin = Zanjir::instance();
	$plugin->run();
	return $plugin;
}
