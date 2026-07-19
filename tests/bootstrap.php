<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/../' );

$plugin_dir = dirname( __DIR__ ) . '/';

require_once $plugin_dir . 'includes/class-zanjir-loader.php';
require_once $plugin_dir . 'includes/db/class-zanjir-db.php';
require_once $plugin_dir . 'includes/class-zanjir-settings.php';
require_once $plugin_dir . 'includes/class-zanjir-roles.php';
require_once $plugin_dir . 'includes/class-zanjir-national-id-validator.php';
require_once $plugin_dir . 'includes/tree/class-zanjir-tree-service.php';
require_once $plugin_dir . 'includes/commission/class-zanjir-matrix.php';
require_once $plugin_dir . 'includes/commission/class-zanjir-commission-engine.php';
require_once $plugin_dir . 'includes/class-zanjir-order-observer.php';
require_once $plugin_dir . 'includes/class-zanjir-registration.php';
require_once $plugin_dir . 'includes/class-zanjir-referral-code.php';
require_once $plugin_dir . 'includes/class-zanjir-commission-lifecycle.php';
require_once $plugin_dir . 'includes/class-zanjir-refund-handler.php';
require_once $plugin_dir . 'includes/class-zanjir.php';
