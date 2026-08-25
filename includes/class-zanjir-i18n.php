<?php
/**
 * Translate stored slugs (status, kind, type) for display.
 *
 * @package Zanjir
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_I18n {

	/**
	 * Human-readable label for a stored slug.
	 *
	 * @param string $slug Raw status, kind, type, or event key.
	 * @return string
	 */
	public static function label( $slug ) {
		$slug   = (string) $slug;
		$labels = self::labels();

		return isset( $labels[ $slug ] ) ? $labels[ $slug ] : $slug;
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels() {
		return array(
			'pending'        => __( 'Pending', 'zanjir' ),
			'payable'        => __( 'Payable', 'zanjir' ),
			'paid'           => __( 'Paid', 'zanjir' ),
			'void'           => __( 'Void', 'zanjir' ),
			'requested'      => __( 'Requested', 'zanjir' ),
			'approved'       => __( 'Approved', 'zanjir' ),
			'rejected'       => __( 'Rejected', 'zanjir' ),
			'draft'          => __( 'Draft', 'zanjir' ),
			'reviewed'       => __( 'Reviewed', 'zanjir' ),
			'affiliate'      => __( 'Affiliate', 'zanjir' ),
			'staff'          => __( 'Staff', 'zanjir' ),
			'tree'           => __( 'Tree', 'zanjir' ),
			'staff_override' => __( 'Staff override', 'zanjir' ),
			'bonus'          => __( 'Bonus', 'zanjir' ),
			'sales_volume'   => __( 'Sales volume', 'zanjir' ),
			'order_count'    => __( 'Order count', 'zanjir' ),
			'fixed'          => __( 'Fixed', 'zanjir' ),
			'rate'           => __( 'Rate (basis-10000)', 'zanjir' ),
			'self_buy'       => __( 'Self-purchase', 'zanjir' ),
			'own_chain'      => __( 'Own referral chain', 'zanjir' ),
			'ip_seen'        => __( 'IP seen', 'zanjir' ),
			'critical'       => __( 'Critical', 'zanjir' ),
			'info'           => __( 'Info', 'zanjir' ),
		);
	}
}
