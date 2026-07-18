<?php
/**
 * Database schema and migration engine.
 *
 * @package Zanjir\DB
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_DB {

	/**
	 * Current database version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Get the table prefix.
	 *
	 * @return string
	 */
	private static function prefix() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_';
	}

	/**
	 * Get the current DB version from options.
	 *
	 * @return string
	 */
	public static function get_version() {
		return get_option( 'zanjir_db_version', '0' );
	}

	/**
	 * Run migration if needed.
	 */
	public static function maybe_upgrade() {
		$current = self::get_version();
		if ( version_compare( $current, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( 'zanjir_db_version', self::DB_VERSION );
		}
	}

	/**
	 * Create or update all tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$p       = self::prefix();

		$sql = "CREATE TABLE {$p}affiliates (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  type            ENUM('affiliate','staff') NOT NULL DEFAULT 'affiliate',
  status          ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
  national_id_hash CHAR(64) NOT NULL,
  national_id_enc  VARBINARY(255) NULL,
  recruit_enabled TINYINT(1) NOT NULL DEFAULT 0,
  annual_sales    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  approved_at     DATETIME NULL,
  created_at      DATETIME NOT NULL,
  updated_at      DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user (user_id),
  UNIQUE KEY uq_national (national_id_hash),
  KEY idx_status (status),
  KEY idx_type (type)
) $charset;

CREATE TABLE {$p}tree (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,
  parent_id      BIGINT UNSIGNED NULL,
  staff_id       BIGINT UNSIGNED NULL,
  depth          INT UNSIGNED NOT NULL DEFAULT 0,
  path           VARCHAR(255) NOT NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_affiliate (affiliate_id),
  KEY idx_parent (parent_id),
  KEY idx_staff (staff_id),
  KEY idx_path (path)
) $charset;

CREATE TABLE {$p}referral_codes (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id  BIGINT UNSIGNED NOT NULL,
  code          VARCHAR(64) NOT NULL,
  discount_enabled TINYINT(1) NOT NULL DEFAULT 0,
  discount_rate INT UNSIGNED NOT NULL DEFAULT 0,
  active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_code (code),
  KEY idx_affiliate (affiliate_id)
) $charset;

CREATE TABLE {$p}order_snapshots (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  referral_code  VARCHAR(64) NULL,
  seller_affiliate_id BIGINT UNSIGNED NULL,
  base_amount    BIGINT UNSIGNED NOT NULL,
  tree_cap_rate  INT UNSIGNED NOT NULL,
  staff_rate     INT UNSIGNED NOT NULL,
  matrix_json    LONGTEXT NOT NULL,
  chain_json     LONGTEXT NOT NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_order (order_id),
  KEY idx_seller (seller_affiliate_id)
) $charset;

CREATE TABLE {$p}commissions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  snapshot_id    BIGINT UNSIGNED NOT NULL,
  beneficiary_id BIGINT UNSIGNED NOT NULL,
  kind           ENUM('tree','staff_override','bonus') NOT NULL,
  tier_level     INT UNSIGNED NULL,
  rate           INT UNSIGNED NOT NULL,
  amount         BIGINT UNSIGNED NOT NULL,
  status         ENUM('pending','payable','paid','void') NOT NULL DEFAULT 'pending',
  return_window_ends_at DATETIME NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_order (order_id),
  KEY idx_beneficiary (beneficiary_id),
  KEY idx_status (status),
  KEY idx_window (return_window_ends_at)
) $charset;

CREATE TABLE {$p}wallet_ledger (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,
  entry_type     ENUM('credit','debit') NOT NULL,
  bucket         ENUM('pending','payable','withdrawable') NOT NULL,
  amount         BIGINT UNSIGNED NOT NULL,
  balance_after  BIGINT NOT NULL,
  ref_type       VARCHAR(32) NOT NULL,
  ref_id         BIGINT UNSIGNED NULL,
  note           VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_affiliate_bucket (affiliate_id, bucket),
  KEY idx_ref (ref_type, ref_id)
) $charset;

CREATE TABLE {$p}withdrawals (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  affiliate_id   BIGINT UNSIGNED NOT NULL,
  amount         BIGINT UNSIGNED NOT NULL,
  status         ENUM('requested','approved','rejected','paid') NOT NULL DEFAULT 'requested',
  iban           VARCHAR(34) NULL,
  admin_note     VARCHAR(255) NULL,
  requested_at   DATETIME NOT NULL,
  processed_at   DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_affiliate (affiliate_id),
  KEY idx_status (status)
) $charset;

CREATE TABLE {$p}settlements (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_start   DATE NOT NULL,
  period_end     DATE NOT NULL,
  total_amount   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status         ENUM('draft','reviewed','approved') NOT NULL DEFAULT 'draft',
  approved_by    BIGINT UNSIGNED NULL,
  approved_at    DATETIME NULL,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_status (status)
) $charset;

CREATE TABLE {$p}bonus_plans (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title          VARCHAR(128) NOT NULL,
  metric         ENUM('sales_volume','order_count') NOT NULL,
  threshold      BIGINT UNSIGNED NOT NULL,
  reward_type    ENUM('fixed','rate') NOT NULL,
  reward_value   BIGINT UNSIGNED NOT NULL,
  period_type    ENUM('monthly','quarterly','yearly','custom') NOT NULL,
  active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_active (active)
) $charset;

CREATE TABLE {$p}fraud_logs (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  event_type     VARCHAR(48) NOT NULL,
  severity       ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  order_id       BIGINT UNSIGNED NULL,
  affiliate_id   BIGINT UNSIGNED NULL,
  ip_hash        CHAR(64) NULL,
  meta_json      LONGTEXT NULL,
  reviewed       TINYINT(1) NOT NULL DEFAULT 0,
  created_at     DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY idx_event (event_type),
  KEY idx_reviewed (reviewed),
  KEY idx_affiliate (affiliate_id)
) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			'affiliates',
			'tree',
			'referral_codes',
			'order_snapshots',
			'commissions',
			'wallet_ledger',
			'withdrawals',
			'settlements',
			'bonus_plans',
			'fraud_logs',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}zanjir_{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		delete_option( 'zanjir_db_version' );
		delete_option( 'zanjir_settings' );
	}
}
