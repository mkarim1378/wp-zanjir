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
	 * Whether the current singular page is a configured Zanjir page.
	 *
	 * @return bool
	 */
	private function is_configured_zanjir_page() {
		if ( ! is_singular( 'page' ) ) {
			return false;
		}

		$page_id = (int) get_queried_object_id();
		if ( $page_id <= 0 ) {
			return false;
		}

		$dash_id = (int) Zanjir_Settings::get( 'dashboard_page_id', 0 );
		$reg_id  = (int) Zanjir_Settings::get( 'register_page_id', 0 );

		return ( $dash_id > 0 && $page_id === $dash_id ) || ( $reg_id > 0 && $page_id === $reg_id );
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

		if ( $this->is_configured_zanjir_page() ) {
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
	 * Build a paragraph with an optional link.
	 *
	 * @param string $message Plain message.
	 * @param string $url     Optional URL.
	 * @param string $label   Optional link label.
	 * @return string
	 */
	private function message_with_link( $message, $url = '', $label = '' ) {
		$html = '<p class="zanjir-notice">' . esc_html( $message );
		if ( $url && $label ) {
			$html .= ' <a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$html .= '</p>';
		return $html;
	}

	/**
	 * Status message when the current user already has an affiliate row.
	 *
	 * @param object $affiliate Affiliate row.
	 * @return string
	 */
	private function render_register_status_message( $affiliate ) {
		$status = isset( $affiliate->status ) ? (string) $affiliate->status : '';

		switch ( $status ) {
			case 'pending':
				return $this->message_with_link(
					__( 'Your affiliate registration is pending admin approval.', 'zanjir' )
				);
			case 'rejected':
				return $this->message_with_link(
					__( 'Your affiliate registration was rejected. Contact support if you need a review.', 'zanjir' )
				);
			case 'suspended':
				return $this->message_with_link(
					__( 'Your affiliate account is suspended. Contact support for details.', 'zanjir' )
				);
			case 'approved':
				$dash_url = class_exists( 'Zanjir_Access_Gate' ) ? Zanjir_Access_Gate::dashboard_url() : '';
				return $this->message_with_link(
					__( 'You are already an approved affiliate.', 'zanjir' ),
					$dash_url,
					$dash_url ? __( 'Go to dashboard', 'zanjir' ) : ''
				);
			default:
				return $this->message_with_link(
					sprintf(
						/* translators: %s: status */
						__( 'You are already registered (status: %s).', 'zanjir' ),
						Zanjir_I18n::label( $status )
					)
				);
		}
	}

	/**
	 * Affiliate registration form.
	 *
	 * @return string
	 */
	public function render_register_form() {
		$this->enqueue_public_assets();

		if ( ! is_user_logged_in() ) {
			$redirect = get_permalink();
			$login    = class_exists( 'Zanjir_Access_Gate' )
				? Zanjir_Access_Gate::login_url( $redirect ? $redirect : '' )
				: wp_login_url( $redirect ? $redirect : '' );
			return $this->message_with_link(
				__( 'Please log in to register as an affiliate.', 'zanjir' ),
				$login,
				__( 'Log in', 'zanjir' )
			);
		}

		$user_id  = get_current_user_id();
		$existing = Zanjir_Registration::get_affiliate_by_user( $user_id );
		if ( $existing ) {
			return $this->render_register_status_message( $existing );
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

		$reg_url = class_exists( 'Zanjir_Access_Gate' ) ? Zanjir_Access_Gate::register_url() : '';

		if ( ! is_user_logged_in() ) {
			$redirect = get_permalink();
			$login    = class_exists( 'Zanjir_Access_Gate' )
				? Zanjir_Access_Gate::login_url( $redirect ? $redirect : '' )
				: wp_login_url( $redirect ? $redirect : '' );
			return $this->message_with_link(
				__( 'Please log in to view your affiliate dashboard.', 'zanjir' ),
				$login,
				__( 'Log in', 'zanjir' )
			);
		}

		$affiliate = Zanjir_Registration::get_affiliate_by_user( get_current_user_id() );

		if ( Zanjir_Roles::can_manage() && ( ! $affiliate || 'approved' !== $affiliate->status ) ) {
			return $this->message_with_link(
				__( 'No approved affiliate account is linked to this user. Dashboard data is only shown for approved affiliates.', 'zanjir' ),
				$reg_url,
				$reg_url ? __( 'Go to registration', 'zanjir' ) : ''
			);
		}

		if ( ! $affiliate || 'approved' !== $affiliate->status ) {
			if ( $affiliate && 'pending' === $affiliate->status ) {
				return $this->message_with_link(
					__( 'Your affiliate registration is pending admin approval.', 'zanjir' ),
					$reg_url,
					$reg_url ? __( 'Go to registration', 'zanjir' ) : ''
				);
			}
			if ( $affiliate && 'rejected' === $affiliate->status ) {
				return $this->message_with_link(
					__( 'Your affiliate registration was rejected. Contact support if you need a review.', 'zanjir' ),
					$reg_url,
					$reg_url ? __( 'Go to registration', 'zanjir' ) : ''
				);
			}
			if ( $affiliate && 'suspended' === $affiliate->status ) {
				return $this->message_with_link(
					__( 'Your affiliate account is suspended. Contact support for details.', 'zanjir' ),
					$reg_url,
					$reg_url ? __( 'Go to registration', 'zanjir' ) : ''
				);
			}

			return $this->message_with_link(
				__( 'Approved affiliate account required.', 'zanjir' ),
				$reg_url,
				$reg_url ? __( 'Go to registration', 'zanjir' ) : ''
			);
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
