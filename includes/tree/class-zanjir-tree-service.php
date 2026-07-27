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

			// Loop if the chosen parent is already under the new node.
			if ( self::is_descendant( (int) $parent_id, (int) $affiliate_id ) ) {
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
	 * Check if $descendant_id is under $ancestor_id in the tree.
	 *
	 * @param int $descendant_id Potential descendant affiliate ID.
	 * @param int $ancestor_id   Potential ancestor affiliate ID.
	 * @return bool
	 */
	public static function is_descendant( $descendant_id, $ancestor_id ) {
		global $wpdb;

		$t = self::table();

		$ancestor = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $ancestor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $ancestor ) {
			return false;
		}

		$descendant = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $descendant_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $descendant ) {
			return false;
		}

		return strpos( $descendant->path, $ancestor->path ) === 0;
	}

	/**
	 * Get a single tree node by affiliate ID.
	 *
	 * @param int $affiliate_id
	 * @return object|null
	 */
	public static function get_node( $affiliate_id ) {
		global $wpdb;

		$t = self::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Resolve the full upline chain for an affiliate (parents only).
	 *
	 * Ordered from closest parent to root, capped at $max_depth.
	 *
	 * @param int $affiliate_id
	 * @param int $max_depth Maximum ancestors to return.
	 * @return array<int, object>
	 */
	public static function resolve_upline_chain( $affiliate_id, $max_depth = 3 ) {
		global $wpdb;

		$t    = self::table();
		$self = $wpdb->get_row( $wpdb->prepare( "SELECT path FROM {$t} WHERE affiliate_id = %d", $affiliate_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		if ( ! $self ) {
			return array();
		}

		$path = rtrim( $self->path, '/' );
		$ids  = array_values(
			array_filter(
				array_map( 'intval', explode( '/', $path ) )
			)
		);

		$self_key = array_search( (int) $affiliate_id, $ids, true );
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
		$results      = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$wpdb->prefix}zanjir_affiliates a ON a.id = t.affiliate_id
			 WHERE t.affiliate_id IN ({$placeholders})
			 ORDER BY t.depth DESC",
			$ids
		) );

		if ( ! $results ) {
			return array();
		}

		$by_id = array();
		foreach ( $results as $row ) {
			$by_id[ (int) $row->affiliate_id ] = $row;
		}

		$ordered = array();
		foreach ( $ids as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$ordered[] = $by_id[ $id ];
			}
		}

		return $ordered;
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

		$t     = self::table();
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
