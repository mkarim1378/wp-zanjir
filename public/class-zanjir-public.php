<?php
/**
 * Public affiliate dashboard shortcodes and registration form.
 *
 * @package Zanjir\Public
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Public {

	/**
	 * Whether front-end assets were enqueued this request.
	 *
	 * @var bool
	 */
	private static $assets_enqueued = false;

	/**
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'init', $this, 'register_shortcodes' );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_assets' );
	}

	/**
	 * Detect Zanjir shortcodes in post content (classic, block, or nested).
	 *
	 * @param string $content Post content.
	 * @return bool
	 */
	private function content_has_zanjir_shortcode( $content ) {
		if ( has_shortcode( $content, 'zanjir_register' ) || has_shortcode( $content, 'zanjir_dashboard' ) ) {
			return true;
		}

		if ( false !== strpos( $content, '[zanjir_register' ) || false !== strpos( $content, '[zanjir_dashboard' ) ) {
			return true;
		}

		if ( false !== strpos( $content, 'zanjir_register' ) || false !== strpos( $content, 'zanjir_dashboard' ) ) {
			return (bool) preg_match( '/<!--\s*wp:shortcode/', $content );
		}

		return false;
	}

	/**
	 * Enqueue dashboard/registration styles (idempotent).
	 */
	private function enqueue_public_assets() {
		if ( self::$assets_enqueued ) {
			return;
		}

		wp_enqueue_style(
			'zanjir-public',
			ZANJIR_PLUGIN_URL . 'assets/css/zanjir-public.css',
			array(),
			ZANJIR_VERSION
		);

		if ( is_rtl() ) {
			wp_enqueue_style(
				'zanjir-public-rtl',
				ZANJIR_PLUGIN_URL . 'assets/css/zanjir-public-rtl.css',
				array( 'zanjir-public' ),
				ZANJIR_VERSION
			);
		}

		self::$assets_enqueued = true;
	}

	/**
	 * Front-end styles (LTR + RTL).
	 */
	public function enqueue_assets() {
		if ( apply_filters( 'zanjir_force_enqueue', false ) ) {
			$this->enqueue_public_assets();
			return;
		}

		if ( ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		if ( ! $this->content_has_zanjir_shortcode( $post->post_content ) ) {
			return;
		}

		$this->enqueue_public_assets();
	}

	/**
	 * Register shortcodes.
	 */
	public function register_shortcodes() {
		add_shortcode( 'zanjir_register', array( $this, 'render_register_form' ) );
		add_shortcode( 'zanjir_dashboard', array( $this, 'render_dashboard' ) );
	}

	/**
	 * Affiliate registration form.
	 *
	 * @return string
	 */
	public function render_register_form() {
		$this->enqueue_public_assets();

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to register as an affiliate.', 'zanjir' ) . '</p>';
		}

		$user_id = get_current_user_id();
		$existing = Zanjir_Registration::get_affiliate_by_user( $user_id );
		if ( $existing ) {
			return '<p>' . esc_html(
				sprintf(
					/* translators: %s: status */
					__( 'You are already registered (status: %s).', 'zanjir' ),
					Zanjir_I18n::label( $existing->status )
				)
			) . '</p>';
		}

		$error   = get_transient( 'zanjir_reg_error_' . $user_id );
		$success = get_transient( 'zanjir_reg_success_' . $user_id );
		if ( $error ) {
			delete_transient( 'zanjir_reg_error_' . $user_id );
		}
		if ( $success ) {
			delete_transient( 'zanjir_reg_success_' . $user_id );
		}

		$show_labels       = (int) Zanjir_Settings::get( 'register_show_labels', 1 );
		$show_placeholders = (int) Zanjir_Settings::get( 'register_show_placeholders', 0 );
		$national_id_text  = __( 'National ID', 'zanjir' );
		$referral_text     = __( 'Referral code (optional)', 'zanjir' );

		ob_start();
		?>
		<div class="zanjir-register">
			<?php if ( $error ) : ?>
				<p class="zanjir-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<?php if ( $success ) : ?>
				<p class="zanjir-success" role="status"><?php echo esc_html( $success ); ?></p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field( Zanjir_Registration::NONCE_ACTION, Zanjir_Registration::NONCE_FIELD ); ?>
				<p>
					<?php if ( $show_labels ) : ?>
						<label for="zanjir_national_id"><?php echo esc_html( $national_id_text ); ?></label><br />
					<?php endif; ?>
					<input
						type="text"
						id="zanjir_national_id"
						name="zanjir_national_id"
						required
						maxlength="10"
						autocomplete="off"
						<?php if ( $show_placeholders ) : ?>
							placeholder="<?php echo esc_attr( $national_id_text ); ?>"
						<?php endif; ?>
						<?php if ( ! $show_labels ) : ?>
							aria-label="<?php echo esc_attr( $national_id_text ); ?>"
						<?php endif; ?>
					/>
				</p>
				<p>
					<?php if ( $show_labels ) : ?>
						<label for="zanjir_referral_code"><?php echo esc_html( $referral_text ); ?></label><br />
					<?php endif; ?>
					<input
						type="text"
						id="zanjir_referral_code"
						name="zanjir_referral_code"
						<?php if ( $show_placeholders ) : ?>
							placeholder="<?php echo esc_attr( $referral_text ); ?>"
						<?php endif; ?>
						<?php if ( ! $show_labels ) : ?>
							aria-label="<?php echo esc_attr( $referral_text ); ?>"
						<?php endif; ?>
					/>
				</p>
				<p><button type="submit"><?php esc_html_e( 'Submit registration', 'zanjir' ); ?></button></p>
			</form>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Affiliate dashboard: link, balances, withdrawals.
	 *
	 * @return string
	 */
	public function render_dashboard() {
		$this->enqueue_public_assets();

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to view your affiliate dashboard.', 'zanjir' ) . '</p>';
		}

		$affiliate = Zanjir_Registration::get_affiliate_by_user( get_current_user_id() );
		if ( ! $affiliate || 'approved' !== $affiliate->status ) {
			return '<p>' . esc_html__( 'Approved affiliate account required.', 'zanjir' ) . '</p>';
		}

		$aff_id       = (int) $affiliate->id;
		$link         = Zanjir_Referral_Code::get_link( $aff_id );
		$pending      = Zanjir_Ledger::get_balance( $aff_id, 'pending' );
		$payable      = Zanjir_Ledger::get_balance( $aff_id, 'payable' );
		$withdrawable = Zanjir_Ledger::get_withdrawable( $aff_id );
		$available    = Zanjir_Withdrawal_Service::available_balance( $aff_id );
		$can_recruit  = Zanjir_Recruit_Service::can_recruit( $aff_id );
		$withdrawals  = Zanjir_Withdrawal_Service::list_for_affiliate( $aff_id, 10 );

		$user_id = get_current_user_id();
		$error   = get_transient( 'zanjir_wd_error_' . $user_id );
		$success = get_transient( 'zanjir_wd_success_' . $user_id );
		if ( $error ) {
			delete_transient( 'zanjir_wd_error_' . $user_id );
		}
		if ( $success ) {
			delete_transient( 'zanjir_wd_success_' . $user_id );
		}

		ob_start();
		?>
		<div class="zanjir-dashboard">
			<h2><?php esc_html_e( 'Affiliate dashboard', 'zanjir' ); ?></h2>
			<?php if ( $error ) : ?>
				<p class="zanjir-error" role="alert"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>
			<?php if ( $success ) : ?>
				<p class="zanjir-success" role="status"><?php echo esc_html( $success ); ?></p>
			<?php endif; ?>
			<p>
				<strong><?php esc_html_e( 'Referral link:', 'zanjir' ); ?></strong>
				<?php if ( $link ) : ?>
					<code><?php echo esc_url( $link ); ?></code>
				<?php else : ?>
					—
				<?php endif; ?>
			</p>
			<ul>
				<li><?php printf( esc_html__( 'Pending: %s', 'zanjir' ), esc_html( number_format_i18n( $pending ) ) ); ?></li>
				<li><?php printf( esc_html__( 'Payable: %s', 'zanjir' ), esc_html( number_format_i18n( $payable ) ) ); ?></li>
				<li><?php printf( esc_html__( 'Withdrawable: %s', 'zanjir' ), esc_html( number_format_i18n( $withdrawable ) ) ); ?></li>
				<li><?php printf( esc_html__( 'Available to request: %s', 'zanjir' ), esc_html( number_format_i18n( $available ) ) ); ?></li>
				<li><?php echo $can_recruit ? esc_html__( 'Recruitment: enabled', 'zanjir' ) : esc_html__( 'Recruitment: locked', 'zanjir' ); ?></li>
			</ul>

			<h3><?php esc_html_e( 'Request withdrawal', 'zanjir' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="zanjir_withdrawal_request" />
				<?php wp_nonce_field( Zanjir_Withdrawal_Service::USER_NONCE ); ?>
				<p>
					<label for="zanjir_wd_amount"><?php esc_html_e( 'Amount (Rial)', 'zanjir' ); ?></label><br />
					<input type="number" id="zanjir_wd_amount" name="amount" min="1" required />
				</p>
				<p>
					<label for="zanjir_wd_iban"><?php esc_html_e( 'IBAN', 'zanjir' ); ?></label><br />
					<input type="text" id="zanjir_wd_iban" name="iban" required maxlength="34" pattern="IR[0-9]{24}" placeholder="IRxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" />
				</p>
				<p><button type="submit"><?php esc_html_e( 'Submit request', 'zanjir' ); ?></button></p>
			</form>

			<h3><?php esc_html_e( 'Recent withdrawals', 'zanjir' ); ?></h3>
			<?php if ( empty( $withdrawals ) ) : ?>
				<p><?php esc_html_e( 'No withdrawals yet.', 'zanjir' ); ?></p>
			<?php else : ?>
				<ul>
					<?php foreach ( $withdrawals as $wd ) : ?>
						<li>
							#<?php echo esc_html( (string) $wd->id ); ?> —
							<?php echo esc_html( number_format_i18n( (int) $wd->amount ) ); ?> —
							<?php echo esc_html( Zanjir_I18n::label( $wd->status ) ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
