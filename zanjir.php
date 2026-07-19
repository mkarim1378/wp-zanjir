<?php
/**
 * Plugin Name: Zanjir
 * Plugin URI:  https://github.com/mkarim1378/wp-zanjir
 * Description: Multi-tier affiliate marketing plugin for WooCommerce with matrix-based commissions, anti-fraud suite, and internal wallet.
 * Version:     1.3.0
 * Author:      محمد کریم قصبه
 * Author-URI:  https://m-karim.ir
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zanjir
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZANJIR_VERSION', '1.3.0' );
define( 'ZANJIR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZANJIR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZANJIR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-loader.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/db/class-zanjir-db.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-settings.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-roles.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-national-id-validator.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/tree/class-zanjir-tree-service.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/commission/class-zanjir-matrix.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/commission/class-zanjir-commission-engine.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir-order-observer.php';
require_once ZANJIR_PLUGIN_DIR . 'includes/class-zanjir.php';

function zanjir() {
    $plugin = Zanjir::instance();
    $plugin->run();
    return $plugin;
}

register_activation_hook( __FILE__, array( 'Zanjir', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Zanjir', 'deactivate' ) );

zanjir();
