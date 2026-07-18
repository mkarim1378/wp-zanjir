<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ . '/../' );

require_once ABSPATH . 'wp-admin/includes/plugin.php';

require_once dirname( __DIR__ ) . '/includes/class-zanjir-loader.php';
require_once dirname( __DIR__ ) . '/includes/class-zanjir.php';
