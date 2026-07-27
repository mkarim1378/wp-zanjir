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
		$loader->add_action( 'admin_post_zanjir_settlement_prepare', $this, 'handle_settlement_prepare' );
		$loader->add_action( 'admin_post_zanjir_settlement_review', $this, 'handle_settlement_review' );
		$loader->add_action( 'admin_post_zanjir_settlement_approve', $this, 'handle_settlement_approve' );
		$loader->add_action( 'admin_post_zanjir_fraud_review', $this, 'handle_fraud_review' );
		$loader->add_action( 'admin_post_zanjir_bonus_create', $this, 'handle_bonus_create' );
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

		add_submenu_page(
			'zanjir',
			__( 'Settings', 'zanjir' ),
			__( 'Settings', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'zanjir',
			__( 'Settlements', 'zanjir' ),
			__( 'Settlements', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-settlements',
			array( $this, 'render_settlements_page' )
		);

		add_submenu_page(
			'zanjir',
			__( 'Withdrawals', 'zanjir' ),
			__( 'Withdrawals', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-withdrawals',
			array( $this, 'render_withdrawals_page' )
		);

		add_submenu_page(
			'zanjir',
			__( 'Affiliates', 'zanjir' ),
			__( 'Affiliates', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-affiliates',
			array( $this, 'render_affiliates_page' )
		);

		add_submenu_page(
			'zanjir',
			__( 'Fraud queue', 'zanjir' ),
			__( 'Fraud queue', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-fraud',
			array( $this, 'render_fraud_page' )
		);

		add_submenu_page(
			'zanjir',
			__( 'Bonus plans', 'zanjir' ),
			__( 'Bonus plans', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-bonus',
			array( $this, 'render_bonus_page' )
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

	/**
	 * Settlements admin page.
	 */
	public function render_settlements_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$payable = Zanjir_Settlement_Service::payable_total();
		$list    = Zanjir_Settlement_Service::list_recent( 30 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zanjir Settlements', 'zanjir' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: payable total in Rial */
					esc_html__( 'Current payable total: %s Rial', 'zanjir' ),
					esc_html( number_format_i18n( $payable ) )
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="zanjir_settlement_prepare" />
				<?php wp_nonce_field( 'zanjir_settlement_prepare' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="period_start"><?php esc_html_e( 'Period start', 'zanjir' ); ?></label></th>
						<td><input type="date" id="period_start" name="period_start" required value="<?php echo esc_attr( gmdate( 'Y-m-01' ) ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="period_end"><?php esc_html_e( 'Period end', 'zanjir' ); ?></label></th>
						<td><input type="date" id="period_end" name="period_end" required value="<?php echo esc_attr( gmdate( 'Y-m-t' ) ); ?>" /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Prepare draft batch', 'zanjir' ) ); ?>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Period', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Total', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $list ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No settlements yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $list as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( $row->period_start . ' → ' . $row->period_end ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row->total_amount ) ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td>
								<?php if ( 'draft' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_settlement_review&id=' . (int) $row->id ), 'zanjir_settlement_' . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Mark reviewed', 'zanjir' ); ?>
									</a>
									|
								<?php endif; ?>
								<?php if ( in_array( $row->status, array( 'draft', 'reviewed' ), true ) ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_settlement_approve&id=' . (int) $row->id ), 'zanjir_settlement_' . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Approve', 'zanjir' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Withdrawals admin page.
	 */
	public function render_withdrawals_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$list = Zanjir_Withdrawal_Service::list_by_status( '', 50 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zanjir Withdrawals', 'zanjir' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Affiliate', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'IBAN', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $list ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No withdrawals yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $list as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( (string) $row->affiliate_id ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row->amount ) ); ?></td>
							<td><?php echo esc_html( (string) $row->iban ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td>
								<?php
								$nonce_action = Zanjir_Withdrawal_Service::ADMIN_NONCE . (int) $row->id;
								if ( 'requested' === $row->status ) :
									?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_withdrawal_approve&id=' . (int) $row->id ), $nonce_action ) ); ?>"><?php esc_html_e( 'Approve', 'zanjir' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_withdrawal_reject&id=' . (int) $row->id ), $nonce_action ) ); ?>"><?php esc_html_e( 'Reject', 'zanjir' ); ?></a>
								<?php elseif ( 'approved' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_withdrawal_paid&id=' . (int) $row->id ), $nonce_action ) ); ?>"><?php esc_html_e( 'Mark paid', 'zanjir' ); ?></a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_withdrawal_reject&id=' . (int) $row->id ), $nonce_action ) ); ?>"><?php esc_html_e( 'Reject', 'zanjir' ); ?></a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Create draft settlement batch.
	 */
	public function handle_settlement_prepare() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		check_admin_referer( 'zanjir_settlement_prepare' );

		$start = isset( $_POST['period_start'] ) ? sanitize_text_field( wp_unslash( $_POST['period_start'] ) ) : '';
		$end   = isset( $_POST['period_end'] ) ? sanitize_text_field( wp_unslash( $_POST['period_end'] ) ) : '';

		Zanjir_Settlement_Service::prepare_batch( $start, $end );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-settlements&done=prepared' ) );
		exit;
	}

	/**
	 * Mark settlement reviewed.
	 */
	public function handle_settlement_review() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'zanjir_settlement_' . $id );
		Zanjir_Settlement_Service::mark_reviewed( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-settlements&done=reviewed' ) );
		exit;
	}

	/**
	 * Approve settlement batch.
	 */
	public function handle_settlement_approve() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'zanjir_settlement_' . $id );
		Zanjir_Settlement_Service::approve( $id, get_current_user_id() );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-settlements&done=approved' ) );
		exit;
	}

	/**
	 * Affiliates list with approve/reject links.
	 */
	public function render_affiliates_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		global $wpdb;
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT * FROM {$wpdb->prefix}zanjir_affiliates ORDER BY id DESC LIMIT 100"
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zanjir Affiliates', 'zanjir' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'User', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Recruit', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No affiliates yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( (string) $row->user_id ); ?></td>
							<td><?php echo esc_html( $row->type ); ?></td>
							<td><?php echo esc_html( $row->status ); ?></td>
							<td><?php echo ! empty( $row->recruit_enabled ) ? esc_html__( 'yes', 'zanjir' ) : esc_html__( 'no', 'zanjir' ); ?></td>
							<td>
								<?php if ( 'pending' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_approve_affiliate&affiliate_id=' . (int) $row->id ), Zanjir_Registration::ADMIN_NONCE . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Approve', 'zanjir' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_reject_affiliate&affiliate_id=' . (int) $row->id ), Zanjir_Registration::ADMIN_NONCE . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Reject', 'zanjir' ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Fraud review queue.
	 */
	public function render_fraud_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$rows = Zanjir_Fraud_Guard::list_unreviewed( 100 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Fraud queue', 'zanjir' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Event', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Severity', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Order', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Affiliate', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No unreviewed events.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( $row->event_type ); ?></td>
							<td><?php echo esc_html( $row->severity ); ?></td>
							<td><?php echo esc_html( (string) $row->order_id ); ?></td>
							<td><?php echo esc_html( (string) $row->affiliate_id ); ?></td>
							<td>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_fraud_review&id=' . (int) $row->id ), 'zanjir_fraud_' . (int) $row->id ) ); ?>">
									<?php esc_html_e( 'Mark reviewed', 'zanjir' ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Bonus plans admin.
	 */
	public function render_bonus_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$plans = Zanjir_Bonus_Service::list_active();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bonus plans', 'zanjir' ); ?></h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="zanjir_bonus_create" />
				<?php wp_nonce_field( 'zanjir_bonus_create' ); ?>
				<table class="form-table">
					<tr>
						<th><label for="bonus_title"><?php esc_html_e( 'Title', 'zanjir' ); ?></label></th>
						<td><input type="text" id="bonus_title" name="title" required class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="bonus_metric"><?php esc_html_e( 'Metric', 'zanjir' ); ?></label></th>
						<td>
							<select id="bonus_metric" name="metric">
								<option value="sales_volume"><?php esc_html_e( 'Sales volume', 'zanjir' ); ?></option>
								<option value="order_count"><?php esc_html_e( 'Order count', 'zanjir' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="bonus_threshold"><?php esc_html_e( 'Threshold', 'zanjir' ); ?></label></th>
						<td><input type="number" id="bonus_threshold" name="threshold" min="0" required /></td>
					</tr>
					<tr>
						<th><label for="bonus_reward_type"><?php esc_html_e( 'Reward type', 'zanjir' ); ?></label></th>
						<td>
							<select id="bonus_reward_type" name="reward_type">
								<option value="fixed"><?php esc_html_e( 'Fixed', 'zanjir' ); ?></option>
								<option value="rate"><?php esc_html_e( 'Rate (basis-10000)', 'zanjir' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="bonus_reward_value"><?php esc_html_e( 'Reward value', 'zanjir' ); ?></label></th>
						<td><input type="number" id="bonus_reward_value" name="reward_value" min="0" required /></td>
					</tr>
				</table>
				<?php submit_button( __( 'Create plan', 'zanjir' ) ); ?>
			</form>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Title', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Metric', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Threshold', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Reward', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $plans ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No active plans.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $plans as $plan ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $plan->id ); ?></td>
							<td><?php echo esc_html( $plan->title ); ?></td>
							<td><?php echo esc_html( $plan->metric ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $plan->threshold ) ); ?></td>
							<td><?php echo esc_html( $plan->reward_type . ':' . number_format_i18n( (int) $plan->reward_value ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Mark fraud log reviewed.
	 */
	public function handle_fraud_review() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'zanjir_fraud_' . $id );
		Zanjir_Fraud_Guard::mark_reviewed( $id );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-fraud&done=reviewed' ) );
		exit;
	}

	/**
	 * Create bonus plan.
	 */
	public function handle_bonus_create() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		check_admin_referer( 'zanjir_bonus_create' );

		Zanjir_Bonus_Service::create_plan(
			array(
				'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'metric'       => isset( $_POST['metric'] ) ? sanitize_key( wp_unslash( $_POST['metric'] ) ) : 'sales_volume',
				'threshold'    => isset( $_POST['threshold'] ) ? absint( $_POST['threshold'] ) : 0,
				'reward_type'  => isset( $_POST['reward_type'] ) ? sanitize_key( wp_unslash( $_POST['reward_type'] ) ) : 'fixed',
				'reward_value' => isset( $_POST['reward_value'] ) ? absint( $_POST['reward_value'] ) : 0,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-bonus&done=created' ) );
		exit;
	}
}
