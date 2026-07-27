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
delete_option( 'zanjir_nid_key' );

// Pending parent options and cron cleanup are best-effort.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'zanjir_pending_parent_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
wp_clear_scheduled_hook( 'zanjir_check_return_window' );
wp_clear_scheduled_hook( 'zanjir_recalc_annual_cap' );
wp_clear_scheduled_hook( 'zanjir_bonus_evaluate' );
