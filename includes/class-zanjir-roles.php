<?php
/**
 * Roles and capabilities management.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Roles {

	/**
	 * Custom capabilities.
	 */
	const CAP_MANAGE    = 'manage_zanjir';
	const CAP_AFFILIATE = 'zanjir_affiliate';

	/**
	 * Affiliate role slug.
	 */
	const ROLE_AFFILIATE = 'zanjir_affiliate';

	/**
	 * Staff role slug.
	 */
	const ROLE_STAFF = 'zanjir_staff';

	/**
	 * Register roles and capabilities on activation.
	 */
	public static function activate() {
		add_role( self::ROLE_AFFILIATE, __( 'Zanjir Affiliate', 'zanjir' ), array(
			'read' => true,
		) );

		add_role( self::ROLE_STAFF, __( 'Zanjir Staff', 'zanjir' ), array(
			'read' => true,
		) );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP_MANAGE );
		}
	}

	/**
	 * Remove roles and capabilities on deactivation.
	 */
	public static function deactivate() {
		remove_role( self::ROLE_AFFILIATE );
		remove_role( self::ROLE_STAFF );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->remove_cap( self::CAP_MANAGE );
		}
	}

	/**
	 * Check if current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function can_manage() {
		return current_user_can( self::CAP_MANAGE );
	}

	/**
	 * Get the affiliate role object.
	 *
	 * @return WP_Role|null
	 */
	public static function get_affiliate_role() {
		return get_role( self::ROLE_AFFILIATE );
	}

	/**
	 * Get the staff role object.
	 *
	 * @return WP_Role|null
	 */
	public static function get_staff_role() {
		return get_role( self::ROLE_STAFF );
	}

	/**
	 * Assign affiliate role to a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function assign_affiliate( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->add_role( self::ROLE_AFFILIATE );
		}
	}

	/**
	 * Assign staff role to a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function assign_staff( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->add_role( self::ROLE_STAFF );
		}
	}

	/**
	 * Remove affiliate role from a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function remove_affiliate( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->remove_role( self::ROLE_AFFILIATE );
		}
	}

	/**
	 * Remove staff role from a user.
	 *
	 * @param int $user_id User ID.
	 */
	public static function remove_staff( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user ) {
			$user->remove_role( self::ROLE_STAFF );
		}
	}
}
