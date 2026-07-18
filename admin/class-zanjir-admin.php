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
			'manage_options',
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
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
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
	 * Sanitize settings before save.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$defaults  = Zanjir_Settings::defaults();
		$sanitized = array();

		$sanitized['tree_depth']     = isset( $input['tree_depth'] ) ? absint( $input['tree_depth'] ) : $defaults['tree_depth'];
		$sanitized['tree_cap']       = isset( $input['tree_cap'] ) ? absint( $input['tree_cap'] ) : $defaults['tree_cap'];
		$sanitized['staff_rate']     = isset( $input['staff_rate'] ) ? absint( $input['staff_rate'] ) : $defaults['staff_rate'];
		$sanitized['bonus_pool']     = isset( $input['bonus_pool'] ) ? absint( $input['bonus_pool'] ) : $defaults['bonus_pool'];
		$sanitized['refund_window']  = isset( $input['refund_window'] ) ? absint( $input['refund_window'] ) : $defaults['refund_window'];
		$sanitized['discount_enabled'] = ! empty( $input['discount_enabled'] ) ? 1 : 0;
		$sanitized['coupon_compat']  = ! empty( $input['coupon_compat'] ) ? 1 : 0;
		$sanitized['double_dip']     = ! empty( $input['double_dip'] ) ? 1 : 0;
		$sanitized['max_discount']   = isset( $input['max_discount'] ) ? absint( $input['max_discount'] ) : $defaults['max_discount'];
		$sanitized['annual_cap']     = isset( $input['annual_cap'] ) ? absint( $input['annual_cap'] ) : $defaults['annual_cap'];
		$sanitized['affiliate_code_len'] = isset( $input['affiliate_code_len'] ) ? absint( $input['affiliate_code_len'] ) : $defaults['affiliate_code_len'];

		return $sanitized;
	}
}
