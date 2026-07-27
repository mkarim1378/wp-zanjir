<?php
/**
 * Admin page registration (settings page skeleton).
 *
 * @package Zanjir\Admin
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Admin {

	/**
	 * Register admin hooks.
	 *
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_menu', $this, 'add_menu' );
		$loader->add_action( 'admin_init', $this, 'register_settings' );
	}

	/**
	 * Add admin menu item.
	 */
	public function add_menu() {
		add_menu_page(
			__( 'Zanjir', 'zanjir' ),
			__( 'Zanjir', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir',
			array( $this, 'render_settings_page' ),
			'dashicons-share',
			80
		);
	}

	/**
	 * Register settings fields.
	 */
	public function register_settings() {
		register_setting( 'zanjir_settings_group', Zanjir_Settings::OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
			'default'           => Zanjir_Settings::defaults(),
			'capability'        => Zanjir_Roles::CAP_MANAGE,
		) );

		add_settings_section(
			'zanjir_commission',
			__( 'Commission', 'zanjir' ),
			array( $this, 'commission_section' ),
			'zanjir-settings'
		);

		add_settings_field(
			'tree_depth',
			__( 'Tree Depth', 'zanjir' ),
			array( $this, 'render_number_field' ),
			'zanjir-settings',
			'zanjir_commission',
			array( 'key' => 'tree_depth', 'min' => 1, 'max' => 3 )
		);

		add_settings_field(
			'tree_cap',
			__( 'Tree Cap (basis-10000)', 'zanjir' ),
			array( $this, 'render_number_field' ),
			'zanjir-settings',
			'zanjir_commission',
			array( 'key' => 'tree_cap', 'min' => 0, 'max' => 10000 )
		);

		add_settings_field(
			'staff_rate',
			__( 'Staff Override (basis-10000)', 'zanjir' ),
			array( $this, 'render_number_field' ),
			'zanjir-settings',
			'zanjir_commission',
			array( 'key' => 'staff_rate', 'min' => 0, 'max' => 10000 )
		);

		add_settings_field(
			'bonus_pool',
			__( 'Bonus Pool (basis-10000)', 'zanjir' ),
			array( $this, 'render_number_field' ),
			'zanjir-settings',
			'zanjir_commission',
			array( 'key' => 'bonus_pool', 'min' => 0, 'max' => 10000 )
		);

		add_settings_section(
			'zanjir_discount',
			__( 'Discount & Double-Dip', 'zanjir' ),
			array( $this, 'discount_section' ),
			'zanjir-settings'
		);

		add_settings_field(
			'discount_enabled',
			__( 'Enable Referral Discount', 'zanjir' ),
			array( $this, 'render_checkbox_field' ),
			'zanjir-settings',
			'zanjir_discount',
			array( 'key' => 'discount_enabled' )
		);

		add_settings_field(
			'coupon_compat',
			__( 'Coupon Compatibility', 'zanjir' ),
			array( $this, 'render_checkbox_field' ),
			'zanjir-settings',
			'zanjir_discount',
			array( 'key' => 'coupon_compat' )
		);

		add_settings_field(
			'max_discount',
			__( 'Max Total Discount (basis-10000)', 'zanjir' ),
			array( $this, 'render_number_field' ),
			'zanjir-settings',
			'zanjir_discount',
			array( 'key' => 'max_discount', 'min' => 0, 'max' => 10000 )
		);

		add_settings_field(
			'double_dip',
			__( 'Double-Dip (Discount + Commission)', 'zanjir' ),
			array( $this, 'render_checkbox_field' ),
			'zanjir-settings',
			'zanjir_discount',
			array( 'key' => 'double_dip', 'description' => __( 'WARNING: When disabled, orders with referral discount will NOT generate commissions.', 'zanjir' ) )
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zanjir Settings', 'zanjir' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'zanjir_settings_group' );
				do_settings_sections( 'zanjir-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Commission section description.
	 */
	public function commission_section() {
		echo '<p>' . esc_html__( 'Configure commission rates and tree structure.', 'zanjir' ) . '</p>';
	}

	/**
	 * Discount section description.
	 */
	public function discount_section() {
		echo '<p>' . esc_html__( 'Configure referral discount and double-dip behavior.', 'zanjir' ) . '</p>';
	}

	/**
	 * Render a number input field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$value = Zanjir_Settings::get( $args['key'], '' );
		printf(
			'<input type="number" name="%s[%s]" value="%s" min="%d" max="%d" class="small-text" />',
			esc_attr( Zanjir_Settings::OPTION_KEY ),
			esc_attr( $args['key'] ),
			esc_attr( $value ),
			intval( $args['min'] ),
			intval( $args['max'] )
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$value = Zanjir_Settings::get( $args['key'], 0 );
		printf(
			'<input type="checkbox" name="%s[%s]" value="1" %s />',
			esc_attr( Zanjir_Settings::OPTION_KEY ),
			esc_attr( $args['key'] ),
			checked( 1, $value, false )
		);
		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Sanitize settings before save.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$current   = Zanjir_Settings::all();
		$defaults  = Zanjir_Settings::defaults();
		$sanitized = $current;

		if ( ! is_array( $input ) ) {
			$input = array();
		}

		$sanitized['tree_depth']         = isset( $input['tree_depth'] ) ? absint( $input['tree_depth'] ) : (int) $current['tree_depth'];
		$sanitized['tree_cap']           = isset( $input['tree_cap'] ) ? absint( $input['tree_cap'] ) : (int) $current['tree_cap'];
		$sanitized['staff_rate']         = isset( $input['staff_rate'] ) ? absint( $input['staff_rate'] ) : (int) $current['staff_rate'];
		$sanitized['bonus_pool']         = isset( $input['bonus_pool'] ) ? absint( $input['bonus_pool'] ) : (int) $current['bonus_pool'];
		$sanitized['refund_window']      = isset( $input['refund_window'] ) ? absint( $input['refund_window'] ) : (int) ( isset( $current['refund_window'] ) ? $current['refund_window'] : $defaults['refund_window'] );
		$sanitized['discount_enabled']   = ! empty( $input['discount_enabled'] ) ? 1 : 0;
		$sanitized['coupon_compat']      = ! empty( $input['coupon_compat'] ) ? 1 : 0;
		$sanitized['double_dip']         = ! empty( $input['double_dip'] ) ? 1 : 0;
		$sanitized['max_discount']       = isset( $input['max_discount'] ) ? absint( $input['max_discount'] ) : (int) $current['max_discount'];
		$sanitized['annual_cap']         = isset( $input['annual_cap'] ) ? absint( $input['annual_cap'] ) : (int) ( isset( $current['annual_cap'] ) ? $current['annual_cap'] : $defaults['annual_cap'] );
		$sanitized['affiliate_code_len'] = isset( $input['affiliate_code_len'] ) ? absint( $input['affiliate_code_len'] ) : (int) ( isset( $current['affiliate_code_len'] ) ? $current['affiliate_code_len'] : $defaults['affiliate_code_len'] );

		// Preserve matrix and any other keys not edited by this form.
		if ( isset( $current['matrix'] ) ) {
			$sanitized['matrix'] = $current['matrix'];
		}

		Zanjir_Settings::flush_cache();

		return $sanitized;
	}
}
