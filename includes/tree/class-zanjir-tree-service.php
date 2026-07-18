<?php
/**
 * Referral tree service with materialized path.
 *
 * @package Zanjir\Tree
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Tree_Service {

	/**
	 * Get the tree table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_tree';
	}

	/**
	 * Insert an affiliate into the tree under a parent.
	 *
	 * @param int      $affiliate_id
	 * @param int|null $parent_id Parent affiliate ID (null = root).
	 * @param int|null $staff_id  Staff member who recruited (for override).
	 * @return true|WP_Error
	 */
	public static function insert( $affiliate_id, $parent_id = null, $staff_id = null ) {
		global $wpdb;

		$t = self::table();

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $exists ) {
			return new WP_Error( 'already_in_tree', __( 'Affiliate already exists in the tree.', 'zanjir' ) );
		}

		if ( null !== $parent_id ) {
			$parent = $wpdb->get_row( $wpdb->prepare( "SELECT id, depth, path FROM {$t} WHERE affiliate_id = %d", $parent_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( ! $parent ) {
				return new WP_Error( 'parent_not_found', __( 'Parent affiliate not found in tree.', 'zanjir' ) );
			}

			if ( self::is_descendant( $affiliate_id, $parent_id ) ) {
				return new WP_Error( 'referral_loop', __( 'Referral loop detected.', 'zanjir' ) );
			}

			$depth = (int) $parent->depth + 1;
			$path  = rtrim( $parent->path, '/' ) . '/' . $affiliate_id . '/';
		} else {
			$depth = 0;
			$path  = '/' . $affiliate_id . '/';
		}

		$insert = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t,
			array(
				'affiliate_id' => $affiliate_id,
				'parent_id'    => $parent_id,
				'staff_id'     => $staff_id,
				'depth'        => $depth,
				'path'         => $path,
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $insert ) {
			return new WP_Error( 'db_error', __( 'Failed to insert into tree.', 'zanjir' ) );
		}

		return true;
	}

	/**
	 * Check if candidate_id is an ancestor of target_id (loop detection).
	 *
	 * @param int $candidate_id The potential new node.
	 * @param int $parent_id    The parent under which we want to insert.
	 * @return bool True if inserting would create a loop.
	 */
	public static function is_descendant( $candidate_id, $parent_id ) {
		global $wpdb;

		$t      = self::table();
		$parent = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $parent_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $parent ) {
			return false;
		}

		$candidate = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $candidate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $candidate ) {
			return false;
		}

		return strpos( $candidate->path, $parent->path ) === 0;
	}

	/**
	 * Resolve the full upline chain for an affiliate.
	 *
	 * @param int $affiliate_id
	 * @param int $max_depth Maximum ancestors to return.
	 * @return array<int, object> Ordered from closest parent to root.
	 */
	public static function resolve_upline_chain( $affiliate_id, $max_depth = 3 ) {
		global $wpdb;

		$t    = self::table();
		$self = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $self ) {
			return array();
		}

		$path = rtrim( $self->path, '/' );
		$ids  = array_filter( explode( '/', $path ) );

		$self_key = array_search( $affiliate_id, $ids, true );
		if ( false !== $self_key ) {
			unset( $ids[ $self_key ] );
		}

		$ids = array_values( $ids );
		$ids = array_slice( $ids, -$max_depth );
		$ids = array_reverse( $ids );

		if ( empty( $ids ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$results = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$wpdb->prefix}zanjir_affiliates a ON a.id = t.affiliate_id
			 WHERE t.affiliate_id IN ({$placeholders})
			 ORDER BY t.depth DESC",
			$ids
		) );

		return $results ? $results : array();
	}

	/**
	 * Get direct children of an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return array
	 */
	public static function get_children( $affiliate_id ) {
		global $wpdb;

		$t = self::table();

		return $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$wpdb->prefix}zanjir_affiliates a ON a.id = t.affiliate_id
			 WHERE t.parent_id = %d
			 ORDER BY t.created_at ASC",
			$affiliate_id
		) );
	}

	/**
	 * Get depth of an affiliate.
	 *
	 * @param int $affiliate_id
	 * @return int|false
	 */
	public static function get_depth( $affiliate_id ) {
		global $wpdb;

		$t    = self::table();
		$depth = $wpdb->get_var( $wpdb->prepare( "SELECT depth FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		return false !== $depth ? (int) $depth : false;
	}

	/**
	 * Remove an affiliate and all descendants from the tree.
	 *
	 * @param int $affiliate_id
	 * @return bool
	 */
	public static function remove( $affiliate_id ) {
		global $wpdb;

		$t    = self::table();
		$self = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $self ) {
			return false;
		}

		$wpdb->query( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"DELETE FROM {$t} WHERE path LIKE %s",
			$self->path . '%'
		) );

		return true;
	}
}
