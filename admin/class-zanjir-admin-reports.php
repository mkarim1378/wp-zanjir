<?php
/**
 * Admin reports UI: commissions, settlements, withdrawals, tree.
 *
 * @package Zanjir\Admin
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Admin_Reports {

	/**
	 * @param Zanjir_Loader $loader
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_menu', $this, 'add_menu', 20 );
		$loader->add_action( 'admin_post_zanjir_reports_export', $this, 'handle_export' );
	}

	/**
	 * Register Reports submenu.
	 */
	public function add_menu() {
		add_submenu_page(
			'zanjir',
			__( 'Reports', 'zanjir' ),
			__( 'Reports', 'zanjir' ),
			Zanjir_Roles::CAP_MANAGE,
			'zanjir-reports',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Main reports screen with tabs.
	 */
	public function render_page() {
		if ( ! Zanjir_Roles::can_manage() ) {
			return;
		}

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'commissions'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$allowed = array( 'commissions', 'settlements', 'withdrawals', 'tree' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'commissions';
		}

		$filters = Zanjir_Reports_Service::normalize_filters( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$base = admin_url( 'admin.php?page=zanjir-reports' );
		?>
		<div class="wrap zanjir-reports">
			<h1><?php esc_html_e( 'Zanjir Reports', 'zanjir' ); ?></h1>

			<nav class="nav-tab-wrapper zanjir-reports-tabs" aria-label="<?php esc_attr_e( 'Report sections', 'zanjir' ); ?>">
				<?php
				$tabs = array(
					'commissions'  => __( 'Commissions', 'zanjir' ),
					'settlements'  => __( 'Settlements', 'zanjir' ),
					'withdrawals'  => __( 'Withdrawals', 'zanjir' ),
					'tree'         => __( 'Referral tree', 'zanjir' ),
				);
				foreach ( $tabs as $slug => $label ) :
					$url   = add_query_arg( 'tab', $slug, $base );
					$class = ( $tab === $slug ) ? 'nav-tab nav-tab-active' : 'nav-tab';
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'settlements':
					$this->render_settlements_tab( $filters, $base );
					break;
				case 'withdrawals':
					$this->render_withdrawals_tab( $filters, $base );
					break;
				case 'tree':
					$this->render_tree_tab( $filters, $base );
					break;
				case 'commissions':
				default:
					$this->render_commissions_tab( $filters, $base );
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * @param array  $filters
	 * @param string $base
	 */
	private function render_commissions_tab( $filters, $base ) {
		$data = Zanjir_Reports_Service::commissions_report( $filters );
		$this->render_summary( $data['sum_amount'], $data['total'], $data['by_status'] );
		$this->render_export_button( 'commissions', $filters );
		?>
		<form method="get" class="zanjir-report-filters">
			<input type="hidden" name="page" value="zanjir-reports" />
			<input type="hidden" name="tab" value="commissions" />
			<label>
				<?php esc_html_e( 'Status', 'zanjir' ); ?>
				<select name="status">
					<option value=""><?php esc_html_e( 'All', 'zanjir' ); ?></option>
					<?php foreach ( array( 'pending', 'payable', 'paid', 'void' ) as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Kind', 'zanjir' ); ?>
				<select name="kind">
					<option value=""><?php esc_html_e( 'All', 'zanjir' ); ?></option>
					<?php foreach ( array( 'tree', 'staff_override', 'bonus' ) as $kind ) : ?>
						<option value="<?php echo esc_attr( $kind ); ?>" <?php selected( $filters['kind'], $kind ); ?>><?php echo esc_html( $kind ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Beneficiary ID', 'zanjir' ); ?>
				<input type="number" name="beneficiary_id" min="0" value="<?php echo esc_attr( (string) $filters['beneficiary_id'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Order ID', 'zanjir' ); ?>
				<input type="number" name="order_id" min="0" value="<?php echo esc_attr( (string) $filters['order_id'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'From', 'zanjir' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'To', 'zanjir' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			</label>
			<?php submit_button( __( 'Filter', 'zanjir' ), 'secondary', '', false ); ?>
		</form>

		<table class="widefat striped zanjir-report-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Order', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Beneficiary', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Kind', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Tier', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Rate', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Window ends', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Created', 'zanjir' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $data['rows'] ) ) : ?>
				<tr><td colspan="10"><?php esc_html_e( 'No commissions match these filters.', 'zanjir' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->id ); ?></td>
						<td>
							<?php
							if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
								$order_link = \Automattic\WooCommerce\Utilities\OrderUtil::get_order_admin_edit_url( (int) $row->order_id );
							} else {
								$order_link = admin_url( 'post.php?post=' . (int) $row->order_id . '&action=edit' );
							}
							?>
							<a href="<?php echo esc_url( $order_link ); ?>"><?php echo esc_html( (string) $row->order_id ); ?></a>
						</td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $row->beneficiary_id ), $base ) ); ?>">
								<?php echo esc_html( (string) $row->beneficiary_id ); ?>
							</a>
						</td>
						<td><?php echo esc_html( (string) $row->kind ); ?></td>
						<td><?php echo esc_html( (string) $row->tier_level ); ?></td>
						<td><?php echo esc_html( (string) $row->rate ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->amount ) ); ?></td>
						<td><span class="zanjir-status zanjir-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
						<td><?php echo esc_html( (string) $row->return_window_ends_at ); ?></td>
						<td><?php echo esc_html( (string) $row->created_at ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( $filters, $data['total'], 'commissions', $base );
	}

	/**
	 * @param array  $filters
	 * @param string $base
	 */
	private function render_settlements_tab( $filters, $base ) {
		$data = Zanjir_Reports_Service::settlements_report( $filters );
		$this->render_summary( $data['sum_amount'], $data['total'], $data['by_status'] );
		$this->render_export_button( 'settlements', $filters );
		?>
		<form method="get" class="zanjir-report-filters">
			<input type="hidden" name="page" value="zanjir-reports" />
			<input type="hidden" name="tab" value="settlements" />
			<label>
				<?php esc_html_e( 'Status', 'zanjir' ); ?>
				<select name="status">
					<option value=""><?php esc_html_e( 'All', 'zanjir' ); ?></option>
					<?php foreach ( array( 'draft', 'reviewed', 'approved' ) as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Period overlaps from', 'zanjir' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'Period overlaps to', 'zanjir' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			</label>
			<?php submit_button( __( 'Filter', 'zanjir' ), 'secondary', '', false ); ?>
		</form>

		<table class="widefat striped zanjir-report-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Period', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Total', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Approved by', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Approved at', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Created', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Ops', 'zanjir' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $data['rows'] ) ) : ?>
				<tr><td colspan="8"><?php esc_html_e( 'No settlements match these filters.', 'zanjir' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->id ); ?></td>
						<td><?php echo esc_html( $row->period_start . ' → ' . $row->period_end ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->total_amount ) ); ?></td>
						<td><span class="zanjir-status zanjir-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
						<td><?php echo esc_html( (string) $row->approved_by ); ?></td>
						<td><?php echo esc_html( (string) $row->approved_at ); ?></td>
						<td><?php echo esc_html( (string) $row->created_at ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=zanjir-settlements' ) ); ?>">
								<?php esc_html_e( 'Open settlements', 'zanjir' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( $filters, $data['total'], 'settlements', $base );
	}

	/**
	 * @param array  $filters
	 * @param string $base
	 */
	private function render_withdrawals_tab( $filters, $base ) {
		$data = Zanjir_Reports_Service::withdrawals_report( $filters );
		$this->render_summary( $data['sum_amount'], $data['total'], $data['by_status'] );
		$this->render_export_button( 'withdrawals', $filters );
		?>
		<form method="get" class="zanjir-report-filters">
			<input type="hidden" name="page" value="zanjir-reports" />
			<input type="hidden" name="tab" value="withdrawals" />
			<label>
				<?php esc_html_e( 'Status', 'zanjir' ); ?>
				<select name="status">
					<option value=""><?php esc_html_e( 'All', 'zanjir' ); ?></option>
					<?php foreach ( array( 'requested', 'approved', 'rejected', 'paid' ) as $st ) : ?>
						<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $filters['status'], $st ); ?>><?php echo esc_html( $st ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Affiliate ID', 'zanjir' ); ?>
				<input type="number" name="affiliate_id" min="0" value="<?php echo esc_attr( (string) $filters['affiliate_id'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'From', 'zanjir' ); ?>
				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>" />
			</label>
			<label>
				<?php esc_html_e( 'To', 'zanjir' ); ?>
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>" />
			</label>
			<?php submit_button( __( 'Filter', 'zanjir' ), 'secondary', '', false ); ?>
		</form>

		<table class="widefat striped zanjir-report-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Affiliate', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Amount', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'IBAN', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Requested', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Processed', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Note', 'zanjir' ); ?></th>
					<th><?php esc_html_e( 'Ops', 'zanjir' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( empty( $data['rows'] ) ) : ?>
				<tr><td colspan="9"><?php esc_html_e( 'No withdrawals match these filters.', 'zanjir' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $data['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( (string) $row->id ); ?></td>
						<td>
							<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $row->affiliate_id ), $base ) ); ?>">
								<?php echo esc_html( (string) $row->affiliate_id ); ?>
							</a>
						</td>
						<td><?php echo esc_html( number_format_i18n( (int) $row->amount ) ); ?></td>
						<td><code><?php echo esc_html( (string) $row->iban ); ?></code></td>
						<td><span class="zanjir-status zanjir-status-<?php echo esc_attr( $row->status ); ?>"><?php echo esc_html( $row->status ); ?></span></td>
						<td><?php echo esc_html( (string) $row->requested_at ); ?></td>
						<td><?php echo esc_html( (string) $row->processed_at ); ?></td>
						<td><?php echo esc_html( (string) $row->admin_note ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=zanjir-withdrawals' ) ); ?>">
								<?php esc_html_e( 'Open withdrawals', 'zanjir' ); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
		$this->render_pagination( $filters, $data['total'], 'withdrawals', $base );
	}

	/**
	 * @param array  $filters
	 * @param string $base
	 */
	private function render_tree_tab( $filters, $base ) {
		$focus_id = $filters['affiliate_id'] > 0 ? $filters['affiliate_id'] : $filters['root_id'];
		$focus    = $focus_id > 0 ? Zanjir_Reports_Service::tree_focus( $focus_id ) : null;
		$upline   = $focus_id > 0 ? Zanjir_Tree_Service::resolve_upline_chain( $focus_id, 10 ) : array();
		$children = $focus_id > 0 ? Zanjir_Tree_Service::get_children( $focus_id ) : array();
		$subtree  = $focus_id > 0 ? Zanjir_Reports_Service::tree_subtree( $focus_id, 500 ) : array();
		$roots    = Zanjir_Reports_Service::tree_roots( 100 );

		$this->render_export_button( 'tree', array_merge( $filters, array( 'root_id' => $focus_id ) ) );
		?>
		<form method="get" class="zanjir-report-filters">
			<input type="hidden" name="page" value="zanjir-reports" />
			<input type="hidden" name="tab" value="tree" />
			<label>
				<?php esc_html_e( 'Affiliate ID', 'zanjir' ); ?>
				<input type="number" name="affiliate_id" min="0" value="<?php echo esc_attr( (string) ( $filters['affiliate_id'] ? $filters['affiliate_id'] : '' ) ); ?>" />
			</label>
			<?php submit_button( __( 'Inspect node', 'zanjir' ), 'secondary', '', false ); ?>
			<?php if ( $focus_id > 0 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'tab', 'tree', $base ) ); ?>"><?php esc_html_e( 'Show roots', 'zanjir' ); ?></a>
			<?php endif; ?>
		</form>

		<?php if ( $focus ) : ?>
			<div class="zanjir-report-summary">
				<p>
					<?php
					printf(
						/* translators: 1: affiliate id, 2: depth, 3: path */
						esc_html__( 'Node #%1$s · depth %2$s · path %3$s · type %4$s · status %5$s · user %6$s', 'zanjir' ),
						esc_html( (string) $focus->affiliate_id ),
						esc_html( (string) $focus->depth ),
						esc_html( (string) $focus->path ),
						esc_html( (string) $focus->type ),
						esc_html( (string) $focus->status ),
						esc_html( (string) $focus->user_id )
					);
					?>
				</p>
				<?php if ( ! empty( $upline ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Upline (closest → root):', 'zanjir' ); ?></strong>
						<?php
						$parts = array();
						foreach ( $upline as $node ) {
							$parts[] = '<a href="' . esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $node->affiliate_id ), $base ) ) . '">#' . (int) $node->affiliate_id . '</a>';
						}
						echo wp_kses_post( implode( ' → ', $parts ) );
						?>
					</p>
				<?php endif; ?>
			</div>

			<h2><?php esc_html_e( 'Direct children', 'zanjir' ); ?></h2>
			<table class="widefat striped zanjir-report-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Affiliate', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'User', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Depth', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $children ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No children.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $children as $row ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $row->affiliate_id ), $base ) ); ?>">#<?php echo esc_html( (string) $row->affiliate_id ); ?></a></td>
							<td><?php echo esc_html( (string) $row->user_id ); ?></td>
							<td><?php echo esc_html( (string) $row->type ); ?></td>
							<td><?php echo esc_html( (string) $row->status ); ?></td>
							<td><?php echo esc_html( (string) $row->depth ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Subtree', 'zanjir' ); ?></h2>
			<ul class="zanjir-tree-list">
				<?php foreach ( $subtree as $row ) : ?>
					<li class="zanjir-tree-node" style="margin-inline-start: <?php echo esc_attr( (string) ( (int) $row->depth * 1.25 ) ); ?>rem;">
						<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $row->affiliate_id ), $base ) ); ?>">
							#<?php echo esc_html( (string) $row->affiliate_id ); ?>
						</a>
						<span class="zanjir-tree-meta">
							<?php
							echo esc_html(
								sprintf(
									'user %1$s · %2$s · %3$s · depth %4$s',
									(string) $row->user_id,
									(string) $row->type,
									(string) $row->status,
									(string) $row->depth
								)
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<h2><?php esc_html_e( 'Root affiliates', 'zanjir' ); ?></h2>
			<table class="widefat striped zanjir-report-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Affiliate', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'User', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Type', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Status', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Path', 'zanjir' ); ?></th>
						<th><?php esc_html_e( 'Children', 'zanjir' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $roots ) ) : ?>
					<tr><td colspan="6"><?php esc_html_e( 'No tree nodes yet.', 'zanjir' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $roots as $row ) : ?>
						<?php $kids = Zanjir_Tree_Service::get_children( (int) $row->affiliate_id ); ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'tree', 'affiliate_id' => (int) $row->affiliate_id ), $base ) ); ?>">
									#<?php echo esc_html( (string) $row->affiliate_id ); ?>
								</a>
							</td>
							<td><?php echo esc_html( (string) $row->user_id ); ?></td>
							<td><?php echo esc_html( (string) $row->type ); ?></td>
							<td><?php echo esc_html( (string) $row->status ); ?></td>
							<td><code><?php echo esc_html( (string) $row->path ); ?></code></td>
							<td><?php echo esc_html( (string) count( $kids ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * @param int   $sum
	 * @param int   $count
	 * @param array $by_status
	 */
	private function render_summary( $sum, $count, $by_status ) {
		?>
		<div class="zanjir-report-summary">
			<p>
				<?php
				printf(
					/* translators: 1: row count, 2: sum amount */
					esc_html__( '%1$s rows · filtered sum %2$s Rial', 'zanjir' ),
					esc_html( number_format_i18n( (int) $count ) ),
					esc_html( number_format_i18n( (int) $sum ) )
				);
				?>
			</p>
			<?php if ( ! empty( $by_status ) ) : ?>
				<ul class="zanjir-report-status-totals">
					<?php foreach ( $by_status as $status => $amount ) : ?>
						<li>
							<span class="zanjir-status zanjir-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
							—
							<?php echo esc_html( number_format_i18n( (int) $amount ) ); ?>
							<?php esc_html_e( 'Rial', 'zanjir' ); ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param string $tab
	 * @param array  $filters
	 */
	private function render_export_button( $tab, $filters ) {
		$args = array_merge(
			array(
				'action' => 'zanjir_reports_export',
				'tab'    => $tab,
			),
			array_filter(
				array(
					'status'         => $filters['status'],
					'kind'           => isset( $filters['kind'] ) ? $filters['kind'] : '',
					'beneficiary_id' => ! empty( $filters['beneficiary_id'] ) ? $filters['beneficiary_id'] : '',
					'affiliate_id'   => ! empty( $filters['affiliate_id'] ) ? $filters['affiliate_id'] : '',
					'order_id'       => ! empty( $filters['order_id'] ) ? $filters['order_id'] : '',
					'date_from'      => $filters['date_from'],
					'date_to'        => $filters['date_to'],
					'root_id'        => ! empty( $filters['root_id'] ) ? $filters['root_id'] : '',
				)
			)
		);
		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'admin-post.php' ) ), 'zanjir_reports_export' );
		?>
		<p class="zanjir-report-export">
			<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Export CSV', 'zanjir' ); ?></a>
		</p>
		<?php
	}

	/**
	 * @param array  $filters
	 * @param int    $total
	 * @param string $tab
	 * @param string $base
	 */
	private function render_pagination( $filters, $total, $tab, $base ) {
		$pages = (int) ceil( $total / Zanjir_Reports_Service::PAGE_SIZE );
		if ( $pages <= 1 ) {
			return;
		}
		$current = $filters['page'];
		?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: total rows */
						esc_html__( '%s items', 'zanjir' ),
						esc_html( number_format_i18n( (int) $total ) )
					);
					?>
				</span>
				<?php
				for ( $p = 1; $p <= $pages && $p <= 20; $p++ ) {
					$args = array(
						'tab'            => $tab,
						'paged'          => $p,
						'status'         => $filters['status'],
						'kind'           => $filters['kind'],
						'beneficiary_id' => $filters['beneficiary_id'] ? $filters['beneficiary_id'] : null,
						'affiliate_id'   => $filters['affiliate_id'] ? $filters['affiliate_id'] : null,
						'order_id'       => $filters['order_id'] ? $filters['order_id'] : null,
						'date_from'      => $filters['date_from'],
						'date_to'        => $filters['date_to'],
					);
					$url = add_query_arg( array_filter( $args, function ( $v ) {
						return null !== $v && '' !== $v && 0 !== $v;
					} ), $base );
					$class = ( $p === $current ) ? 'button button-primary' : 'button';
					printf(
						'<a class="%1$s" href="%2$s">%3$s</a> ',
						esc_attr( $class ),
						esc_url( $url ),
						esc_html( (string) $p )
					);
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Stream CSV download.
	 */
	public function handle_export() {
		if ( ! Zanjir_Roles::can_manage() ) {
			wp_die( esc_html__( 'Unauthorized.', 'zanjir' ) );
		}
		check_admin_referer( 'zanjir_reports_export' );

		$tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'commissions';
		$allowed = array( 'commissions', 'settlements', 'withdrawals', 'tree' );
		if ( ! in_array( $tab, $allowed, true ) ) {
			$tab = 'commissions';
		}

		$filters = Zanjir_Reports_Service::normalize_filters( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$csv     = Zanjir_Reports_Service::export_csv( $tab, $filters );
		$filename = 'zanjir-' . $tab . '-' . gmdate( 'Ymd-His' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV binary download.
		echo $csv;
		exit;
	}
}
