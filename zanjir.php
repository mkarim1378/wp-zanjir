<?php
/**
 * Plugin Name: Zanjir
 * Plugin URI:  https://github.com/mkarim1378/wp-zanjir
 * Description: Multi-tier affiliate marketing plugin for WooCommerce with matrix-based commissions, anti-fraud suite, and internal wallet.
 * Version:     2.2.1
 * Author:      محمد کریم قصبه
 * Author-URI:  https://m-karim.ir
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zanjir
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZANJIR_VERSION', '2.2.1' );
define( 'ZANJIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZANJIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZANJIR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS, etc.).
 *
 * Must run on before_woocommerce_init from the main plugin file.
 */
add_action( 'before_woocommerce_init', function () {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
} );

require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-loader.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/db/class-zanjir-db.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-settings.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-roles.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-national-id-validator.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/tree/class-zanjir-tree-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/commission/class-zanjir-matrix.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/commission/class-zanjir-money.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/commission/class-zanjir-commission-engine.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-discount.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/wallet/class-zanjir-ledger.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/wallet/class-zanjir-settlement-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/wallet/class-zanjir-withdrawal-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/fraud/class-zanjir-fraud-guard.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-recruit-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/bonus/class-zanjir-bonus-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/admin/class-zanjir-reports-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-order-observer.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-commission-lifecycle.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir.php';

function zanjir() {
    $plugin = Zanjir::instance();
    $plugin->run();
    return $plugin;
}

register_activation_hook( __FILE__, array( 'Zanjir', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Zanjir', 'deactivate' ) );

zanjir();
