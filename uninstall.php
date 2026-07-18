<?php
/**
 * Fired when the plugin is uninstalled.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$prefix = $wpdb->prefix . 'zanjir_';

$tables = array(
	'tree',
	'fraud_logs',
	'bonus_plans',
	'withdrawals',
	'settlements',
	'wallet_ledger',
	'commissions',
	'order_snapshots',
	'referral_codes',
	'affiliates',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

delete_option( 'zanjir_settings' );
delete_option( 'zanjir_db_version' );
