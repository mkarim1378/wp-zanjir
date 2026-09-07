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
		$loader->add_action( 'admin_enqueue_scripts', $this, 'enqueue_assets' );
		$loader->add_action( 'admin_post_zanjir_settlement_prepare', $this, 'handle_settlement_prepare' );
		$loader->add_action( 'admin_post_zanjir_settlement_review', $this, 'handle_settlement_review' );
		$loader->add_action( 'admin_post_zanjir_settlement_approve', $this, 'handle_settlement_approve' );
		$loader->add_action( 'admin_post_zanjir_fraud_review', $this, 'handle_fraud_review' );
		$loader->add_action( 'admin_post_zanjir_bonus_create', $this, 'handle_bonus_create' );
		$loader->add_action( 'admin_post_zanjir_set_affiliate_type', $this, 'handle_set_affiliate_type' );
		$loader->add_action( 'admin_post_zanjir_affiliate_discount', $this, 'handle_affiliate_discount' );
	}

	/**
	 * Enqueue admin CSS/JS on Zanjir screens.
	 *
	 * @param string $hook
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'zanjir' ) ) {
			return;
		}

		wp_enqueue_style(
			'zanjir-admin',
			ZANJIR_PLUGIN_URL . 'assets/css/zanjir-admin.css',
			array(),
			ZANJIR_VERSION
		);

		if ( 'toplevel_page_zanjir' === $hook ) {
			wp_enqueue_script(
				'zanjir-admin-settings',
				ZANJIR_PLUGIN_URL . 'assets/js/zanjir-admin-settings.js',
				array(),
				ZANJIR_VERSION,
				true
			);
		}
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
	 * Register settings (single option; UI is custom-rendered).
	 */
	public function register_settings() {
		register_setting( 'zanjir_settings_group', Zanjir_Settings::OPTION_KEY, array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
			'default'           => Zanjir_Settings::defaults(),
			'capability'        => Zanjir_Roles::CAP_MANAGE,
		) );
	}

	/**
	 * Render the unified settings page.
	 */
	public function render_settings_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$settings = Zanjir_Settings::all();
		$tree     = (int) $settings['tree_cap'];
		$staff    = (int) $settings['staff_rate'];
		$bonus    = (int) $settings['bonus_pool'];
		$total    = $tree + $staff + $bonus;
		$pct      = $total / 100;
		$over     = $total > 10000;
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'commission'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed  = array( 'commission', 'matrix', 'discount', 'operations' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'commission';
		}

		$tabs = array(
			'commission' => array(
				'label' => __( 'Commission & budget', 'zanjir' ),
				'hint'  => __( 'Tree depth and share of each order', 'zanjir' ),
				'icon'  => 'dashicons-chart-pie',
			),
			'matrix'     => array(
				'label' => __( 'Commission matrix', 'zanjir' ),
				'hint'  => __( 'Depth × position rate table', 'zanjir' ),
				'icon'  => 'dashicons-networking',
			),
			'discount'   => array(
				'label' => __( 'Discount & Double-Dip', 'zanjir' ),
				'hint'  => __( 'Referral discount rules', 'zanjir' ),
				'icon'  => 'dashicons-tag',
			),
			'operations' => array(
				'label' => __( 'Operations', 'zanjir' ),
				'hint'  => __( 'Refund window, caps, codes', 'zanjir' ),
				'icon'  => 'dashicons-admin-generic',
			),
		);
		?>
		<div class="wrap zanjir-settings-wrap">
			<div class="zanjir-settings-app" data-zanjir-settings>
				<header class="zanjir-settings-hero">
					<div class="zanjir-settings-hero__text">
						<p class="zanjir-settings-eyebrow"><?php esc_html_e( 'Zanjir', 'zanjir' ); ?></p>
						<h1><?php esc_html_e( 'Zanjir Settings', 'zanjir' ); ?></h1>
						<p class="zanjir-settings-lead">
							<?php esc_html_e( 'All commission, discount, and operations options in one place. Switch sections without leaving this page — one save covers everything.', 'zanjir' ); ?>
						</p>
					</div>
					<div class="zanjir-budget-card<?php echo $over ? ' is-over' : ''; ?>" data-budget-card>
						<div class="zanjir-budget-card__ring" aria-hidden="true">
							<svg viewBox="0 0 36 36" class="zanjir-budget-ring">
								<path class="zanjir-budget-ring__track" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
								<path class="zanjir-budget-ring__value" data-budget-ring stroke-dasharray="<?php echo esc_attr( min( 100, $pct ) . ', 100' ); ?>" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
							</svg>
							<div class="zanjir-budget-ring__label">
								<span data-budget-pct><?php echo esc_html( number_format_i18n( $pct, 1 ) ); ?></span><small>%</small>
							</div>
						</div>
						<div class="zanjir-budget-card__meta">
							<p class="zanjir-budget-card__title"><?php esc_html_e( 'Order budget', 'zanjir' ); ?></p>
							<p class="zanjir-budget-card__sum">
								<span data-budget-total><?php echo esc_html( (string) $total ); ?></span>
								<span class="zanjir-budget-card__of">/ 10000</span>
							</p>
							<ul class="zanjir-budget-legend" aria-label="<?php esc_attr_e( 'Budget breakdown', 'zanjir' ); ?>">
								<li><i class="is-tree"></i> <?php esc_html_e( 'Tree', 'zanjir' ); ?> <strong data-budget-tree><?php echo esc_html( (string) $tree ); ?></strong></li>
								<li><i class="is-staff"></i> <?php esc_html_e( 'Staff', 'zanjir' ); ?> <strong data-budget-staff><?php echo esc_html( (string) $staff ); ?></strong></li>
								<li><i class="is-bonus"></i> <?php esc_html_e( 'Bonus', 'zanjir' ); ?> <strong data-budget-bonus><?php echo esc_html( (string) $bonus ); ?></strong></li>
							</ul>
							<p class="zanjir-budget-hint<?php echo $over ? ' is-visible' : ''; ?>" data-budget-hint>
								<?php esc_html_e( 'Total exceeds 10000 (100%). Reduce shares before saving.', 'zanjir' ); ?>
							</p>
						</div>
					</div>
				</header>

				<?php settings_errors( 'zanjir_settings' ); ?>

				<form method="post" action="options.php" class="zanjir-settings-form" id="zanjir-settings-form">
					<?php settings_fields( 'zanjir_settings_group' ); ?>

					<div class="zanjir-settings-shell">
						<nav class="zanjir-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'zanjir' ); ?>">
							<?php foreach ( $tabs as $slug => $meta ) : ?>
								<button
									type="button"
									class="zanjir-settings-nav__item<?php echo $tab === $slug ? ' is-active' : ''; ?>"
									data-tab="<?php echo esc_attr( $slug ); ?>"
									aria-selected="<?php echo $tab === $slug ? 'true' : 'false'; ?>"
								>
									<span class="dashicons <?php echo esc_attr( $meta['icon'] ); ?>" aria-hidden="true"></span>
									<span class="zanjir-settings-nav__copy">
										<span class="zanjir-settings-nav__label"><?php echo esc_html( $meta['label'] ); ?></span>
										<span class="zanjir-settings-nav__hint"><?php echo esc_html( $meta['hint'] ); ?></span>
									</span>
								</button>
							<?php endforeach; ?>
						</nav>

						<div class="zanjir-settings-panels">
							<section class="zanjir-settings-panel<?php echo 'commission' === $tab ? ' is-active' : ''; ?>" data-panel="commission" <?php echo 'commission' === $tab ? '' : 'hidden'; ?>>
								<div class="zanjir-panel-head">
									<h2><?php esc_html_e( 'Commission & budget', 'zanjir' ); ?></h2>
									<p><?php esc_html_e( 'Configure commission rates and tree structure. Values use basis-10000 (10000 = 100%).', 'zanjir' ); ?></p>
								</div>
								<div class="zanjir-field-grid">
									<?php
									$this->render_setting_number(
										array(
											'key'         => 'tree_depth',
											'label'       => __( 'Tree Depth', 'zanjir' ),
											'description' => __( 'How many upline levels earn from a sale (1–3).', 'zanjir' ),
											'min'         => 1,
											'max'         => 3,
										)
									);
									$this->render_setting_number(
										array(
											'key'         => 'tree_cap',
											'label'       => __( 'Tree Cap (basis-10000)', 'zanjir' ),
											'description' => __( 'Total share allocated to the referral tree.', 'zanjir' ),
											'min'         => 0,
											'max'         => 10000,
											'budget'      => 'tree',
										)
									);
									$this->render_setting_number(
										array(
											'key'         => 'staff_rate',
											'label'       => __( 'Staff Override (basis-10000)', 'zanjir' ),
											'description' => __( 'Fixed share for the assigned staff member.', 'zanjir' ),
											'min'         => 0,
											'max'         => 10000,
											'budget'      => 'staff',
										)
									);
									$this->render_setting_number(
										array(
											'key'         => 'bonus_pool',
											'label'       => __( 'Bonus Pool (basis-10000)', 'zanjir' ),
											'description' => __( 'Pool used by bonus plans when targets are met.', 'zanjir' ),
											'min'         => 0,
											'max'         => 10000,
											'budget'      => 'bonus',
										)
									);
									?>
								</div>
							</section>

							<section class="zanjir-settings-panel<?php echo 'matrix' === $tab ? ' is-active' : ''; ?>" data-panel="matrix" <?php echo 'matrix' === $tab ? '' : 'hidden'; ?>>
								<div class="zanjir-panel-head">
									<h2><?php esc_html_e( 'Commission matrix', 'zanjir' ); ?></h2>
									<p><?php esc_html_e( 'Each row depth must match the number of rates, rates must sum to tree_cap, and the seller (first rate) must be highest.', 'zanjir' ); ?></p>
								</div>
								<?php $this->render_matrix_field(); ?>
							</section>

							<section class="zanjir-settings-panel<?php echo 'discount' === $tab ? ' is-active' : ''; ?>" data-panel="discount" <?php echo 'discount' === $tab ? '' : 'hidden'; ?>>
								<div class="zanjir-panel-head">
									<h2><?php esc_html_e( 'Discount & Double-Dip', 'zanjir' ); ?></h2>
									<p><?php esc_html_e( 'Configure referral discount and double-dip behavior.', 'zanjir' ); ?></p>
								</div>
								<div class="zanjir-field-stack">
									<?php
									$this->render_setting_toggle(
										array(
											'key'         => 'discount_enabled',
											'label'       => __( 'Enable Referral Discount', 'zanjir' ),
											'description' => __( 'Allow affiliates to offer a checkout discount via their referral code.', 'zanjir' ),
										)
									);
									?>
									<div class="zanjir-field-grid zanjir-field-grid--single">
										<?php
										$this->render_setting_number(
											array(
												'key'         => 'default_discount_rate',
												'label'       => __( 'Default referral discount (basis-10000)', 'zanjir' ),
												'description' => __( 'Applied to newly generated referral codes when global discount is enabled.', 'zanjir' ),
												'min'         => 0,
												'max'         => 10000,
											)
										);
										?>
									</div>
									<?php
									$this->render_setting_toggle(
										array(
											'key'         => 'coupon_compat',
											'label'       => __( 'Coupon Compatibility', 'zanjir' ),
											'description' => __( 'Allow WooCommerce coupons together with the referral discount.', 'zanjir' ),
										)
									);
									$this->render_setting_toggle(
										array(
											'key'         => 'double_dip',
											'label'       => __( 'Double-Dip (Discount + Commission)', 'zanjir' ),
											'description' => __( 'WARNING: When disabled, orders with referral discount will NOT generate commissions.', 'zanjir' ),
											'warn'        => true,
										)
									);
									?>
									<div class="zanjir-field-grid zanjir-field-grid--single">
										<?php
										$this->render_setting_number(
											array(
												'key'         => 'max_discount',
												'label'       => __( 'Max Total Discount (basis-10000)', 'zanjir' ),
												'description' => __( 'Ceiling for referral discount + other discounts when compatibility is on.', 'zanjir' ),
												'min'         => 0,
												'max'         => 10000,
											)
										);
										?>
									</div>
								</div>
							</section>

							<section class="zanjir-settings-panel<?php echo 'operations' === $tab ? ' is-active' : ''; ?>" data-panel="operations" <?php echo 'operations' === $tab ? '' : 'hidden'; ?>>
								<div class="zanjir-panel-head">
									<h2><?php esc_html_e( 'Operations', 'zanjir' ); ?></h2>
									<p><?php esc_html_e( 'Return window, recruitment cap, and referral code length.', 'zanjir' ); ?></p>
								</div>
								<div class="zanjir-field-grid">
									<?php
									$this->render_setting_number(
										array(
											'key'         => 'refund_window',
											'label'       => __( 'Refund window (days)', 'zanjir' ),
											'description' => __( 'Days before pending commission becomes payable.', 'zanjir' ),
											'min'         => 0,
											'max'         => 365,
										)
									);
									$this->render_setting_select(
										array(
											'key'         => 'commission_trigger_status',
											'label'       => __( 'Commission trigger status', 'zanjir' ),
											'description' => __( 'WooCommerce order status that creates pending commissions.', 'zanjir' ),
											'options'     => array(
												'completed'  => __( 'Completed', 'zanjir' ),
												'processing' => __( 'Processing (after payment)', 'zanjir' ),
											),
										)
									);
									$this->render_setting_number(
										array(
											'key'         => 'annual_cap',
											'label'       => __( 'Annual recruit cap (Rial)', 'zanjir' ),
											'description' => __( 'Yearly recruitment earnings ceiling per affiliate (0 = unlimited).', 'zanjir' ),
											'min'         => 0,
											'max'         => 999999999999,
										)
									);
									$this->render_setting_number(
										array(
											'key'         => 'affiliate_code_len',
											'label'       => __( 'Referral code length', 'zanjir' ),
											'description' => __( 'Characters in newly generated referral codes (4–32).', 'zanjir' ),
											'min'         => 4,
											'max'         => 32,
										)
									);
									?>
								</div>
								<div class="zanjir-panel-head zanjir-panel-head--sub">
									<h3><?php esc_html_e( 'Affiliate pages', 'zanjir' ); ?></h3>
									<p><?php esc_html_e( 'Map WordPress pages used for registration and the dashboard access gate.', 'zanjir' ); ?></p>
								</div>
								<div class="zanjir-field-grid">
									<?php
									$this->render_setting_page(
										array(
											'key'         => 'dashboard_page_id',
											'label'       => __( 'Dashboard page', 'zanjir' ),
											'description' => __( 'Guests and non-approved affiliates are redirected away from this page. Leave empty to disable the gate.', 'zanjir' ),
										)
									);
									$this->render_setting_page(
										array(
											'key'         => 'register_page_id',
											'label'       => __( 'Registration page', 'zanjir' ),
											'description' => __( 'Public signup landing. Non-approved users hitting the dashboard are sent here.', 'zanjir' ),
										)
									);
									$this->render_setting_page(
										array(
											'key'         => 'login_page_id',
											'label'       => __( 'Login page', 'zanjir' ),
											'description' => __( 'Page used for guest login links (e.g. SMS login / Voorodak account button). Falls back to WooCommerce My Account, then wp-login.', 'zanjir' ),
										)
									);
									?>
								</div>
								<div class="zanjir-panel-head zanjir-panel-head--sub">
									<h3><?php esc_html_e( 'Registration form', 'zanjir' ); ?></h3>
									<p><?php esc_html_e( 'Display options for the [zanjir_register] shortcode fields.', 'zanjir' ); ?></p>
								</div>
								<div class="zanjir-field-grid">
									<?php
									$this->render_setting_radio(
										array(
											'key'         => 'register_show_labels',
											'label'       => __( 'Field labels', 'zanjir' ),
											'description' => __( 'Show a label above each registration field.', 'zanjir' ),
											'options'     => array(
												'1' => __( 'Show', 'zanjir' ),
												'0' => __( 'Hide', 'zanjir' ),
											),
										)
									);
									$this->render_setting_radio(
										array(
											'key'         => 'register_show_placeholders',
											'label'       => __( 'Field placeholders', 'zanjir' ),
											'description' => __( 'Show placeholder text inside each registration field.', 'zanjir' ),
											'options'     => array(
												'1' => __( 'Show', 'zanjir' ),
												'0' => __( 'Hide', 'zanjir' ),
											),
										)
									);
									?>
								</div>
							</section>
						</div>
					</div>

					<footer class="zanjir-settings-footer">
						<p class="zanjir-settings-footer__note">
							<?php esc_html_e( 'Changes apply after you save. Matrix rows are validated against the tree cap.', 'zanjir' ); ?>
						</p>
						<?php submit_button( __( 'Save all settings', 'zanjir' ), 'primary large', 'submit', false ); ?>
					</footer>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render matrix editor inputs.
	 */
	public function render_matrix_field() {
		$matrix = Zanjir_Matrix::load();
		$option = Zanjir_Settings::OPTION_KEY;
		?>
		<div class="zanjir-matrix-board">
			<?php foreach ( $matrix as $i => $row ) : ?>
				<?php
				$depth = (int) $row['depth'];
				$cap   = isset( $row['tree_cap'] ) ? (int) $row['tree_cap'] : (int) Zanjir_Settings::get( 'tree_cap', 2000 );
				$rates = array_pad( array_map( 'intval', $row['rates'] ), 3, 0 );
				$rates = array_slice( $rates, 0, 3 );
				$sum   = (int) array_sum( array_slice( $rates, 0, $depth ) );
				$ok    = ( $sum === $cap );
				?>
				<article
					class="zanjir-matrix-card"
					data-matrix-row="<?php echo (int) $i; ?>"
					data-ok-label="<?php echo esc_attr__( 'Balanced', 'zanjir' ); ?>"
					data-bad-label="<?php echo esc_attr__( 'Sum must equal tree cap', 'zanjir' ); ?>"
				>
					<header class="zanjir-matrix-card__head">
						<span class="zanjir-matrix-card__badge"><?php echo esc_html( sprintf( /* translators: %d: row number */ __( 'Row %d', 'zanjir' ), $i + 1 ) ); ?></span>
						<span class="zanjir-matrix-card__status<?php echo $ok ? ' is-ok' : ' is-bad'; ?>" data-matrix-status>
							<?php echo $ok ? esc_html__( 'Balanced', 'zanjir' ) : esc_html__( 'Sum must equal tree cap', 'zanjir' ); ?>
						</span>
					</header>
					<div class="zanjir-matrix-card__grid">
						<label class="zanjir-field">
							<span class="zanjir-field__label"><?php esc_html_e( 'Depth', 'zanjir' ); ?></span>
							<input id="zanjir-matrix-depth-<?php echo (int) $i; ?>" class="zanjir-field__input" type="number" name="<?php echo esc_attr( $option ); ?>[matrix][<?php echo (int) $i; ?>][depth]" value="<?php echo esc_attr( (string) $depth ); ?>" min="1" max="3" data-matrix-depth />
						</label>
						<label class="zanjir-field">
							<span class="zanjir-field__label"><?php esc_html_e( 'Tree cap', 'zanjir' ); ?></span>
							<input id="zanjir-matrix-cap-<?php echo (int) $i; ?>" class="zanjir-field__input" type="number" name="<?php echo esc_attr( $option ); ?>[matrix][<?php echo (int) $i; ?>][tree_cap]" value="<?php echo esc_attr( (string) $cap ); ?>" min="0" max="10000" data-matrix-cap />
						</label>
					</div>
					<div class="zanjir-matrix-rates">
						<p class="zanjir-matrix-rates__title"><?php esc_html_e( 'Rates (basis-10000, seller → upline)', 'zanjir' ); ?></p>
						<div class="zanjir-matrix-rates__row">
							<?php for ( $r = 0; $r < 3; $r++ ) : ?>
								<label class="zanjir-field zanjir-field--tier">
									<span class="zanjir-field__label">
										<?php
										printf(
											/* translators: %d: tier number */
											esc_html__( 'Tier %d', 'zanjir' ),
											$r + 1
										);
										?>
									</span>
									<input
										type="number"
										class="zanjir-field__input"
										name="<?php echo esc_attr( $option ); ?>[matrix][<?php echo (int) $i; ?>][rates][<?php echo (int) $r; ?>]"
										value="<?php echo esc_attr( (string) $rates[ $r ] ); ?>"
										min="0"
										max="10000"
										data-matrix-rate
										aria-label="<?php echo esc_attr( sprintf( __( 'Tier %d', 'zanjir' ), $r + 1 ) ); ?>"
									/>
								</label>
							<?php endfor; ?>
						</div>
						<p
							class="zanjir-matrix-card__foot"
							data-matrix-foot
							data-tpl="<?php echo esc_attr__( 'Using first %1$d rate(s); sum = %2$d (must equal tree cap).', 'zanjir' ); ?>"
						>
							<?php
							printf(
								/* translators: 1: depth, 2: sum of first N rates */
								esc_html__( 'Using first %1$d rate(s); sum = %2$d (must equal tree cap).', 'zanjir' ),
								$depth,
								$sum
							);
							?>
						</p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Number field card.
	 *
	 * @param array $args Field arguments.
	 */
	private function render_setting_number( $args ) {
		$key   = $args['key'];
		$value = Zanjir_Settings::get( $key, '' );
		$id    = 'zanjir-setting-' . $key;
		?>
		<label class="zanjir-field zanjir-field--card" for="<?php echo esc_attr( $id ); ?>">
			<span class="zanjir-field__label"><?php echo esc_html( $args['label'] ); ?></span>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<span class="zanjir-field__help"><?php echo esc_html( $args['description'] ); ?></span>
			<?php endif; ?>
			<input
				id="<?php echo esc_attr( $id ); ?>"
				class="zanjir-field__input"
				type="number"
				name="<?php echo esc_attr( Zanjir_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
				value="<?php echo esc_attr( (string) $value ); ?>"
				min="<?php echo esc_attr( (string) intval( $args['min'] ) ); ?>"
				max="<?php echo esc_attr( (string) intval( $args['max'] ) ); ?>"
				<?php if ( ! empty( $args['budget'] ) ) : ?>
					data-budget-input="<?php echo esc_attr( $args['budget'] ); ?>"
				<?php endif; ?>
			/>
		</label>
		<?php
	}

	/**
	 * Toggle checkbox card.
	 *
	 * @param array $args Field arguments.
	 */
	private function render_setting_toggle( $args ) {
		$key   = $args['key'];
		$value = (int) Zanjir_Settings::get( $key, 0 );
		$id    = 'zanjir-setting-' . $key;
		$warn  = ! empty( $args['warn'] );
		?>
		<label class="zanjir-toggle<?php echo $warn ? ' zanjir-toggle--warn' : ''; ?>" for="<?php echo esc_attr( $id ); ?>">
			<span class="zanjir-toggle__copy">
				<span class="zanjir-toggle__label"><?php echo esc_html( $args['label'] ); ?></span>
				<?php if ( ! empty( $args['description'] ) ) : ?>
					<span class="zanjir-toggle__help"><?php echo esc_html( $args['description'] ); ?></span>
				<?php endif; ?>
			</span>
			<span class="zanjir-toggle__control">
				<input
					id="<?php echo esc_attr( $id ); ?>"
					type="checkbox"
					name="<?php echo esc_attr( Zanjir_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
					value="1"
					<?php checked( 1, $value ); ?>
				/>
				<span class="zanjir-toggle__track" aria-hidden="true"><span class="zanjir-toggle__thumb"></span></span>
			</span>
		</label>
		<?php
	}

	/**
	 * Select field card.
	 *
	 * @param array $args Field arguments.
	 */
	private function render_setting_select( $args ) {
		$key     = $args['key'];
		$value   = Zanjir_Settings::get( $key, '' );
		$id      = 'zanjir-setting-' . $key;
		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		?>
		<label class="zanjir-field zanjir-field--card" for="<?php echo esc_attr( $id ); ?>">
			<span class="zanjir-field__label"><?php echo esc_html( $args['label'] ); ?></span>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<span class="zanjir-field__help"><?php echo esc_html( $args['description'] ); ?></span>
			<?php endif; ?>
			<select
				id="<?php echo esc_attr( $id ); ?>"
				class="zanjir-field__input"
				name="<?php echo esc_attr( Zanjir_Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
			>
				<?php foreach ( $options as $opt_value => $opt_label ) : ?>
					<option value="<?php echo esc_attr( (string) $opt_value ); ?>" <?php selected( (string) $value, (string) $opt_value ); ?>>
						<?php echo esc_html( $opt_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</label>
		<?php
	}

	/**
	 * Page dropdown card.
	 *
	 * @param array $args Field arguments.
	 */
	private function render_setting_page( $args ) {
		$key   = $args['key'];
		$value = (int) Zanjir_Settings::get( $key, 0 );
		$id    = 'zanjir-setting-' . $key;
		$name  = Zanjir_Settings::OPTION_KEY . '[' . $key . ']';
		?>
		<div class="zanjir-field zanjir-field--card">
			<label class="zanjir-field__label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $args['label'] ); ?></label>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<span class="zanjir-field__help"><?php echo esc_html( $args['description'] ); ?></span>
			<?php endif; ?>
			<?php
			wp_dropdown_pages(
				array(
					'id'                => $id,
					'name'              => $name,
					'class'             => 'zanjir-field__input',
					'show_option_none'  => __( '— Select —', 'zanjir' ),
					'option_none_value' => '0',
					'selected'          => $value,
					'post_status'       => array( 'publish', 'private', 'draft' ),
				)
			);
			?>
		</div>
		<?php
	}

	/**
	 * Radio group card.
	 *
	 * @param array $args Field arguments.
	 */
	private function render_setting_radio( $args ) {
		$key     = $args['key'];
		$value   = (string) (int) Zanjir_Settings::get( $key, 0 );
		$id      = 'zanjir-setting-' . $key;
		$options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();
		$name    = Zanjir_Settings::OPTION_KEY . '[' . $key . ']';
		?>
		<fieldset class="zanjir-field zanjir-field--card zanjir-field--radio">
			<legend class="zanjir-field__label"><?php echo esc_html( $args['label'] ); ?></legend>
			<?php if ( ! empty( $args['description'] ) ) : ?>
				<span class="zanjir-field__help"><?php echo esc_html( $args['description'] ); ?></span>
			<?php endif; ?>
			<div class="zanjir-radio-group">
				<?php foreach ( $options as $opt_value => $opt_label ) : ?>
					<?php
					$opt_id = $id . '-' . sanitize_html_class( (string) $opt_value );
					?>
					<label class="zanjir-radio" for="<?php echo esc_attr( $opt_id ); ?>">
						<input
							id="<?php echo esc_attr( $opt_id ); ?>"
							type="radio"
							name="<?php echo esc_attr( $name ); ?>"
							value="<?php echo esc_attr( (string) $opt_value ); ?>"
							<?php checked( $value, (string) $opt_value ); ?>
						/>
						<span><?php echo esc_html( $opt_label ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<?php
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

		$budget_total = (int) $sanitized['tree_cap'] + (int) $sanitized['staff_rate'] + (int) $sanitized['bonus_pool'];
		if ( $budget_total > 10000 ) {
			add_settings_error(
				'zanjir_settings',
				'zanjir_budget_over',
				__( 'Order budget (tree + staff + bonus) cannot exceed 10000 (100%). Settings were not saved.', 'zanjir' ),
				'error'
			);
			$sanitized['tree_cap']   = (int) $current['tree_cap'];
			$sanitized['staff_rate'] = (int) $current['staff_rate'];
			$sanitized['bonus_pool'] = (int) $current['bonus_pool'];
		}

		$sanitized['refund_window']      = isset( $input['refund_window'] ) ? absint( $input['refund_window'] ) : (int) ( isset( $current['refund_window'] ) ? $current['refund_window'] : $defaults['refund_window'] );
		$trigger                         = isset( $input['commission_trigger_status'] ) ? sanitize_key( $input['commission_trigger_status'] ) : ( isset( $current['commission_trigger_status'] ) ? $current['commission_trigger_status'] : $defaults['commission_trigger_status'] );
		$sanitized['commission_trigger_status'] = in_array( $trigger, array( 'processing', 'completed' ), true ) ? $trigger : 'completed';
		$sanitized['discount_enabled']   = ! empty( $input['discount_enabled'] ) ? 1 : 0;
		$sanitized['default_discount_rate'] = isset( $input['default_discount_rate'] ) ? min( 10000, absint( $input['default_discount_rate'] ) ) : (int) ( isset( $current['default_discount_rate'] ) ? $current['default_discount_rate'] : $defaults['default_discount_rate'] );
		$sanitized['coupon_compat']      = ! empty( $input['coupon_compat'] ) ? 1 : 0;
		$sanitized['double_dip']         = ! empty( $input['double_dip'] ) ? 1 : 0;
		$sanitized['max_discount']       = isset( $input['max_discount'] ) ? absint( $input['max_discount'] ) : (int) $current['max_discount'];
		$sanitized['annual_cap']         = isset( $input['annual_cap'] ) ? absint( $input['annual_cap'] ) : (int) ( isset( $current['annual_cap'] ) ? $current['annual_cap'] : $defaults['annual_cap'] );
		$sanitized['affiliate_code_len'] = isset( $input['affiliate_code_len'] ) ? absint( $input['affiliate_code_len'] ) : (int) ( isset( $current['affiliate_code_len'] ) ? $current['affiliate_code_len'] : $defaults['affiliate_code_len'] );

		if ( isset( $input['register_show_labels'] ) ) {
			$sanitized['register_show_labels'] = '0' === (string) $input['register_show_labels'] ? 0 : 1;
		} else {
			$sanitized['register_show_labels'] = (int) ( isset( $current['register_show_labels'] ) ? $current['register_show_labels'] : $defaults['register_show_labels'] );
		}

		if ( isset( $input['register_show_placeholders'] ) ) {
			$sanitized['register_show_placeholders'] = '1' === (string) $input['register_show_placeholders'] ? 1 : 0;
		} else {
			$sanitized['register_show_placeholders'] = (int) ( isset( $current['register_show_placeholders'] ) ? $current['register_show_placeholders'] : $defaults['register_show_placeholders'] );
		}

		$sanitized['dashboard_page_id'] = $this->sanitize_page_id(
			isset( $input['dashboard_page_id'] ) ? $input['dashboard_page_id'] : ( isset( $current['dashboard_page_id'] ) ? $current['dashboard_page_id'] : $defaults['dashboard_page_id'] )
		);
		$sanitized['register_page_id'] = $this->sanitize_page_id(
			isset( $input['register_page_id'] ) ? $input['register_page_id'] : ( isset( $current['register_page_id'] ) ? $current['register_page_id'] : $defaults['register_page_id'] )
		);
		$sanitized['login_page_id'] = $this->sanitize_page_id(
			isset( $input['login_page_id'] ) ? $input['login_page_id'] : ( isset( $current['login_page_id'] ) ? $current['login_page_id'] : $defaults['login_page_id'] )
		);

		if ( isset( $input['matrix'] ) && is_array( $input['matrix'] ) ) {
			$matrix_rows = array();
			foreach ( $input['matrix'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$depth = isset( $row['depth'] ) ? absint( $row['depth'] ) : 0;
				$cap   = isset( $row['tree_cap'] ) ? absint( $row['tree_cap'] ) : (int) $sanitized['tree_cap'];
				$rates = array();
				if ( isset( $row['rates'] ) && is_array( $row['rates'] ) ) {
					foreach ( $row['rates'] as $rate ) {
						$rates[] = absint( $rate );
					}
				}
				$depth = max( 1, min( 3, $depth ) );
				$rates = array_slice( array_pad( $rates, $depth, 0 ), 0, $depth );
				$matrix_rows[] = array(
					'depth'    => $depth,
					'tree_cap' => $cap,
					'rates'    => $rates,
				);
			}

			$validated = Zanjir_Matrix::validate( $matrix_rows );
			if ( is_wp_error( $validated ) ) {
				add_settings_error(
					'zanjir_settings',
					'zanjir_matrix_invalid',
					$validated->get_error_message(),
					'error'
				);
				$sanitized['matrix'] = isset( $current['matrix'] ) ? $current['matrix'] : Zanjir_Matrix::defaults();
			} else {
				$sanitized['matrix'] = $matrix_rows;
			}
		} elseif ( isset( $current['matrix'] ) ) {
			$sanitized['matrix'] = $current['matrix'];
		} else {
			$sanitized['matrix'] = Zanjir_Matrix::defaults();
		}

		Zanjir_Settings::flush_cache();

		return $sanitized;
	}

	/**
	 * Sanitize a WordPress page ID setting.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	private function sanitize_page_id( $value ) {
		$id = absint( $value );
		if ( $id <= 0 ) {
			return 0;
		}

		if ( 'page' !== get_post_type( $id ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Settlements admin page.
	 */
	public function render_settlements_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$payable       = Zanjir_Settlement_Service::payable_total();
		$period_start  = gmdate( 'Y-m-01' );
		$period_end    = gmdate( 'Y-m-t' );
		$period_payable = Zanjir_Settlement_Service::payable_total_in_period( $period_start, $period_end );
		$list          = Zanjir_Settlement_Service::list_recent( 30 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Zanjir Settlements', 'zanjir' ); ?></h1>
			<p>
				<?php
				printf(
					/* translators: %s: payable total in Rial */
					esc_html__( 'Current payable total (all periods): %s Rial', 'zanjir' ),
					esc_html( number_format_i18n( $payable ) )
				);
				?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: period start, 2: period end, 3: payable total in Rial */
					esc_html__( 'Payable in default period (%1$s → %2$s): %3$s Rial', 'zanjir' ),
					esc_html( $period_start ),
					esc_html( $period_end ),
					esc_html( number_format_i18n( $period_payable ) )
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
						<th><?php esc_html_e( 'Commissions', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Total', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $list ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No settlements yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $list as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( $row->period_start . ' → ' . $row->period_end ); ?></td>
							<td><?php echo esc_html( (string) Zanjir_Settlement_Service::item_count( (int) $row->id ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $row->total_amount ) ); ?></td>
							<td><?php echo esc_html( Zanjir_I18n::label( $row->status ) ); ?></td>
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
							<td><?php echo esc_html( Zanjir_I18n::label( $row->status ) ); ?></td>
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

		$result = Zanjir_Settlement_Service::prepare_batch( $start, $end );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				admin_url(
					'admin.php?page=zanjir-settlements&error=' . rawurlencode( $result->get_error_code() )
				)
			);
			exit;
		}

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
		$result = Zanjir_Settlement_Service::approve( $id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				admin_url(
					'admin.php?page=zanjir-settlements&error=' . rawurlencode( $result->get_error_code() )
				)
			);
			exit;
		}

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
			"SELECT a.*, rc.code AS referral_code, rc.discount_enabled AS code_discount_enabled, rc.discount_rate AS code_discount_rate
			 FROM {$wpdb->prefix}zanjir_affiliates a
			 LEFT JOIN {$wpdb->prefix}zanjir_referral_codes rc ON rc.affiliate_id = a.id AND rc.active = 1
			 ORDER BY a.id DESC LIMIT 100"
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
						<th><?php esc_html_e( 'Referral code', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Discount', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No affiliates yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) $row->id ); ?></td>
							<td><?php echo esc_html( (string) $row->user_id ); ?></td>
							<td><?php echo esc_html( Zanjir_I18n::label( $row->type ) ); ?></td>
							<td><?php echo esc_html( Zanjir_I18n::label( $row->status ) ); ?></td>
							<td><?php echo ! empty( $row->recruit_enabled ) ? esc_html__( 'yes', 'zanjir' ) : esc_html__( 'no', 'zanjir' ); ?></td>
							<td><?php echo $row->referral_code ? esc_html( $row->referral_code ) : '—'; ?></td>
							<td>
								<?php if ( 'approved' === $row->status && $row->referral_code ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="zanjir-inline-form">
										<input type="hidden" name="action" value="zanjir_affiliate_discount" />
										<input type="hidden" name="affiliate_id" value="<?php echo esc_attr( (string) $row->id ); ?>" />
										<?php wp_nonce_field( 'zanjir_affiliate_discount_' . (int) $row->id ); ?>
										<label>
											<input type="checkbox" name="discount_enabled" value="1" <?php checked( 1, (int) $row->code_discount_enabled ); ?> />
											<?php esc_html_e( 'On', 'zanjir' ); ?>
										</label>
										<input
											type="number"
											name="discount_rate"
											min="0"
											max="10000"
											value="<?php echo esc_attr( (string) (int) $row->code_discount_rate ); ?>"
											style="width:5em"
											title="<?php esc_attr_e( 'Rate (basis-10000)', 'zanjir' ); ?>"
										/>
										<?php submit_button( __( 'Save', 'zanjir' ), 'secondary small', 'submit', false ); ?>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'pending' === $row->status ) : ?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_approve_affiliate&affiliate_id=' . (int) $row->id ), Zanjir_Registration::ADMIN_NONCE . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Approve', 'zanjir' ); ?>
									</a>
									|
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_reject_affiliate&affiliate_id=' . (int) $row->id ), Zanjir_Registration::ADMIN_NONCE . (int) $row->id ) ); ?>">
										<?php esc_html_e( 'Reject', 'zanjir' ); ?>
									</a>
								<?php elseif ( 'approved' === $row->status ) : ?>
									<?php
									$next_type = ( 'staff' === $row->type ) ? 'affiliate' : 'staff';
									$label     = ( 'staff' === $row->type ) ? __( 'Make affiliate', 'zanjir' ) : __( 'Make staff', 'zanjir' );
									?>
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=zanjir_set_affiliate_type&affiliate_id=' . (int) $row->id . '&type=' . rawurlencode( $next_type ) ), 'zanjir_type_' . (int) $row->id ) ); ?>">
										<?php echo esc_html( $label ); ?>
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
							<td><?php echo esc_html( Zanjir_I18n::label( $row->event_type ) ); ?></td>
							<td><?php echo esc_html( Zanjir_I18n::label( $row->severity ) ); ?></td>
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
							<td><?php echo esc_html( Zanjir_I18n::label( $plan->metric ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( (int) $plan->threshold ) ); ?></td>
							<td><?php echo esc_html( Zanjir_I18n::label( $plan->reward_type ) . ': ' . number_format_i18n( (int) $plan->reward_value ) ); ?></td>
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

		$result = Zanjir_Bonus_Service::create_plan(
			array(
				'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'metric'       => isset( $_POST['metric'] ) ? sanitize_key( wp_unslash( $_POST['metric'] ) ) : 'sales_volume',
				'threshold'    => isset( $_POST['threshold'] ) ? absint( $_POST['threshold'] ) : 0,
				'reward_type'  => isset( $_POST['reward_type'] ) ? sanitize_key( wp_unslash( $_POST['reward_type'] ) ) : 'fixed',
				'reward_value' => isset( $_POST['reward_value'] ) ? absint( $_POST['reward_value'] ) : 0,
			)
		);
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				admin_url(
					'admin.php?page=zanjir-bonus&error=' . rawurlencode( $result->get_error_code() )
				)
			);
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-bonus&done=created' ) );
		exit;
	}

	/**
	 * Update per-affiliate referral discount.
	 */
	public function handle_affiliate_discount() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}

		$affiliate_id = isset( $_POST['affiliate_id'] ) ? absint( $_POST['affiliate_id'] ) : 0;
		check_admin_referer( 'zanjir_affiliate_discount_' . $affiliate_id );

		$enabled = ! empty( $_POST['discount_enabled'] );
		$rate    = isset( $_POST['discount_rate'] ) ? absint( $_POST['discount_rate'] ) : 0;

		$result = Zanjir_Referral_Code::update_discount( $affiliate_id, $enabled, $rate );
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				admin_url(
					'admin.php?page=zanjir-affiliates&error=' . rawurlencode( $result->get_error_code() )
				)
			);
			exit;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-affiliates&done=discount' ) );
		exit;
	}

	/**
	 * Toggle affiliate/staff type.
	 */
	public function handle_set_affiliate_type() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}

		$id   = isset( $_GET['affiliate_id'] ) ? absint( $_GET['affiliate_id'] ) : 0;
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		check_admin_referer( 'zanjir_type_' . $id );

		Zanjir_Registration::set_type( $id, $type );
		wp_safe_redirect( admin_url( 'admin.php?page=zanjir-affiliates&done=type' ) );
		exit;
	}
}
