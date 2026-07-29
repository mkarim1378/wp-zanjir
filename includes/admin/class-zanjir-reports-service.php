<?php
/**
 * Read-only reporting queries for admin screens.
 *
 * @package Zanjir\Admin
 */

defined( 'ABSPATH' ) || exit;

class Zanjir_Reports_Service {

	const PAGE_SIZE = 50;

	/**
	 * @return string
	 */
	private static function commissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_commissions';
	}

	/**
	 * @return string
	 */
	private static function settlements_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_settlements';
	}

	/**
	 * @return string
	 */
	private static function withdrawals_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_withdrawals';
	}

	/**
	 * @return string
	 */
	private static function tree_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_tree';
	}

	/**
	 * @return string
	 */
	private static function affiliates_table() {
		global $wpdb;
		return $wpdb->prefix . 'zanjir_affiliates';
	}

	/**
	 * Normalize list filters from request-like array.
	 *
	 * @param array $input
	 * @return array
	 */
	public static function normalize_filters( $input ) {
		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
		$kind   = isset( $input['kind'] ) ? sanitize_key( $input['kind'] ) : '';
		$from   = isset( $input['date_from'] ) ? sanitize_text_field( $input['date_from'] ) : '';
		$to     = isset( $input['date_to'] ) ? sanitize_text_field( $input['date_to'] ) : '';

		return array(
			'status'        => $status,
			'kind'          => $kind,
			'beneficiary_id'=> isset( $input['beneficiary_id'] ) ? absint( $input['beneficiary_id'] ) : 0,
			'affiliate_id'  => isset( $input['affiliate_id'] ) ? absint( $input['affiliate_id'] ) : 0,
			'order_id'      => isset( $input['order_id'] ) ? absint( $input['order_id'] ) : 0,
			'date_from'     => self::valid_date( $from ) ? $from : '',
			'date_to'       => self::valid_date( $to ) ? $to : '',
			'page'          => max( 1, isset( $input['paged'] ) ? absint( $input['paged'] ) : 1 ),
			'root_id'       => isset( $input['root_id'] ) ? absint( $input['root_id'] ) : 0,
		);
	}

	/**
	 * @param string $date Y-m-d
	 * @return bool
	 */
	private static function valid_date( $date ) {
		if ( ! is_string( $date ) || '' === $date ) {
			return false;
		}
		$dt = DateTime::createFromFormat( 'Y-m-d', $date );
		return $dt && $dt->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Commission rows with filters + pagination.
	 *
	 * @param array $filters
	 * @return array{rows: object[], total: int, sum_amount: int, by_status: array<string,int>}
	 */
	public static function commissions_report( $filters ) {
		global $wpdb;

		$t     = self::commissions_table();
		$where = array( '1=1' );
		$args  = array();

		if ( $filters['status'] && in_array( $filters['status'], array( 'pending', 'payable', 'paid', 'void' ), true ) ) {
			$where[] = 'c.status = %s';
			$args[]  = $filters['status'];
		}
		if ( $filters['kind'] && in_array( $filters['kind'], array( 'tree', 'staff_override', 'bonus' ), true ) ) {
			$where[] = 'c.kind = %s';
			$args[]  = $filters['kind'];
		}
		if ( $filters['beneficiary_id'] > 0 ) {
			$where[] = 'c.beneficiary_id = %d';
			$args[]  = $filters['beneficiary_id'];
		}
		if ( $filters['order_id'] > 0 ) {
			$where[] = 'c.order_id = %d';
			$args[]  = $filters['order_id'];
		}
		if ( $filters['date_from'] ) {
			$where[] = 'c.created_at >= %s';
			$args[]  = $filters['date_from'] . ' 00:00:00';
		}
		if ( $filters['date_to'] ) {
			$where[] = 'c.created_at <= %s';
			$args[]  = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $filters['page'] - 1 ) * self::PAGE_SIZE;

		$count_sql = "SELECT COUNT(*) FROM {$t} c WHERE {$where_sql}";
		$sum_sql   = "SELECT COALESCE(SUM(c.amount), 0) FROM {$t} c WHERE {$where_sql}";
		$status_sql = "SELECT c.status, COALESCE(SUM(c.amount), 0) AS total FROM {$t} c WHERE {$where_sql} GROUP BY c.status";

		if ( empty( $args ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $sum_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $status_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT c.* FROM {$t} c WHERE {$where_sql} ORDER BY c.id DESC LIMIT " . (int) self::PAGE_SIZE . ' OFFSET ' . (int) $offset
			);
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $wpdb->prepare( $sum_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $wpdb->prepare( $status_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$list_args = array_merge( $args, array( self::PAGE_SIZE, $offset ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT c.* FROM {$t} c WHERE {$where_sql} ORDER BY c.id DESC LIMIT %d OFFSET %d",
				$list_args
			) );
		}

		$by_status = array();
		if ( $by ) {
			foreach ( $by as $row ) {
				$by_status[ $row->status ] = (int) $row->total;
			}
		}

		return array(
			'rows'       => $rows ? $rows : array(),
			'total'      => $total,
			'sum_amount' => $sum,
			'by_status'  => $by_status,
		);
	}

	/**
	 * Settlements report.
	 *
	 * @param array $filters
	 * @return array{rows: object[], total: int, sum_amount: int, by_status: array<string,int>}
	 */
	public static function settlements_report( $filters ) {
		global $wpdb;

		$t     = self::settlements_table();
		$where = array( '1=1' );
		$args  = array();

		if ( $filters['status'] && in_array( $filters['status'], array( 'draft', 'reviewed', 'approved' ), true ) ) {
			$where[] = 's.status = %s';
			$args[]  = $filters['status'];
		}
		if ( $filters['date_from'] ) {
			$where[] = 's.period_end >= %s';
			$args[]  = $filters['date_from'];
		}
		if ( $filters['date_to'] ) {
			$where[] = 's.period_start <= %s';
			$args[]  = $filters['date_to'];
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $filters['page'] - 1 ) * self::PAGE_SIZE;

		$count_sql  = "SELECT COUNT(*) FROM {$t} s WHERE {$where_sql}";
		$sum_sql    = "SELECT COALESCE(SUM(s.total_amount), 0) FROM {$t} s WHERE {$where_sql}";
		$status_sql = "SELECT s.status, COALESCE(SUM(s.total_amount), 0) AS total FROM {$t} s WHERE {$where_sql} GROUP BY s.status";

		if ( empty( $args ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $sum_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $status_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT s.* FROM {$t} s WHERE {$where_sql} ORDER BY s.id DESC LIMIT " . (int) self::PAGE_SIZE . ' OFFSET ' . (int) $offset
			);
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $wpdb->prepare( $sum_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $wpdb->prepare( $status_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$list_args = array_merge( $args, array( self::PAGE_SIZE, $offset ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT s.* FROM {$t} s WHERE {$where_sql} ORDER BY s.id DESC LIMIT %d OFFSET %d",
				$list_args
			) );
		}

		$by_status = array();
		if ( $by ) {
			foreach ( $by as $row ) {
				$by_status[ $row->status ] = (int) $row->total;
			}
		}

		return array(
			'rows'       => $rows ? $rows : array(),
			'total'      => $total,
			'sum_amount' => $sum,
			'by_status'  => $by_status,
		);
	}

	/**
	 * Withdrawals report.
	 *
	 * @param array $filters
	 * @return array{rows: object[], total: int, sum_amount: int, by_status: array<string,int>}
	 */
	public static function withdrawals_report( $filters ) {
		global $wpdb;

		$t     = self::withdrawals_table();
		$where = array( '1=1' );
		$args  = array();

		if ( $filters['status'] && in_array( $filters['status'], array( 'requested', 'approved', 'rejected', 'paid' ), true ) ) {
			$where[] = 'w.status = %s';
			$args[]  = $filters['status'];
		}
		if ( $filters['affiliate_id'] > 0 ) {
			$where[] = 'w.affiliate_id = %d';
			$args[]  = $filters['affiliate_id'];
		}
		if ( $filters['date_from'] ) {
			$where[] = 'w.requested_at >= %s';
			$args[]  = $filters['date_from'] . ' 00:00:00';
		}
		if ( $filters['date_to'] ) {
			$where[] = 'w.requested_at <= %s';
			$args[]  = $filters['date_to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $filters['page'] - 1 ) * self::PAGE_SIZE;

		$count_sql  = "SELECT COUNT(*) FROM {$t} w WHERE {$where_sql}";
		$sum_sql    = "SELECT COALESCE(SUM(w.amount), 0) FROM {$t} w WHERE {$where_sql}";
		$status_sql = "SELECT w.status, COALESCE(SUM(w.amount), 0) AS total FROM {$t} w WHERE {$where_sql} GROUP BY w.status";

		if ( empty( $args ) ) {
			$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $sum_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $status_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT w.* FROM {$t} w WHERE {$where_sql} ORDER BY w.id DESC LIMIT " . (int) self::PAGE_SIZE . ' OFFSET ' . (int) $offset
			);
		} else {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$sum   = (int) $wpdb->get_var( $wpdb->prepare( $sum_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$by    = $wpdb->get_results( $wpdb->prepare( $status_sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
			$list_args = array_merge( $args, array( self::PAGE_SIZE, $offset ) );
			$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
				"SELECT w.* FROM {$t} w WHERE {$where_sql} ORDER BY w.id DESC LIMIT %d OFFSET %d",
				$list_args
			) );
		}

		$by_status = array();
		if ( $by ) {
			foreach ( $by as $row ) {
				$by_status[ $row->status ] = (int) $row->total;
			}
		}

		return array(
			'rows'       => $rows ? $rows : array(),
			'total'      => $total,
			'sum_amount' => $sum,
			'by_status'  => $by_status,
		);
	}

	/**
	 * Root nodes in the referral tree.
	 *
	 * @param int $limit
	 * @return object[]
	 */
	public static function tree_roots( $limit = 100 ) {
		global $wpdb;

		$t = self::tree_table();
		$a = self::affiliates_table();

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$a} a ON a.id = t.affiliate_id
			 WHERE t.parent_id IS NULL OR t.parent_id = 0
			 ORDER BY t.affiliate_id ASC
			 LIMIT %d",
			max( 1, (int) $limit )
		) );

		return $rows ? $rows : array();
	}

	/**
	 * Subtree under a node (including the node), ordered by path/depth.
	 *
	 * @param int $affiliate_id
	 * @param int $limit
	 * @return object[]
	 */
	public static function tree_subtree( $affiliate_id, $limit = 500 ) {
		global $wpdb;

		$affiliate_id = (int) $affiliate_id;
		if ( $affiliate_id <= 0 ) {
			return array();
		}

		$node = Zanjir_Tree_Service::get_node( $affiliate_id );
		if ( ! $node || empty( $node->path ) ) {
			return array();
		}

		$t = self::tree_table();
		$a = self::affiliates_table();

		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$a} a ON a.id = t.affiliate_id
			 WHERE t.path LIKE %s
			 ORDER BY t.depth ASC, t.affiliate_id ASC
			 LIMIT %d",
			$wpdb->esc_like( $node->path ) . '%',
			max( 1, (int) $limit )
		) );

		return $rows ? $rows : array();
	}

	/**
	 * Lookup a single affiliate for tree focus view.
	 *
	 * @param int $affiliate_id
	 * @return object|null
	 */
	public static function tree_focus( $affiliate_id ) {
		global $wpdb;

		$affiliate_id = (int) $affiliate_id;
		if ( $affiliate_id <= 0 ) {
			return null;
		}

		$t = self::tree_table();
		$a = self::affiliates_table();

		$row = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT t.*, a.user_id, a.type, a.status
			 FROM {$t} t
			 JOIN {$a} a ON a.id = t.affiliate_id
			 WHERE t.affiliate_id = %d",
			$affiliate_id
		) );

		return $row ? $row : null;
	}

	/**
	 * Build CSV string for a report type.
	 *
	 * @param string $tab commissions|settlements|withdrawals|tree
	 * @param array  $filters
	 * @return string
	 */
	public static function export_csv( $tab, $filters ) {
		$lines = array();

		if ( 'commissions' === $tab ) {
			$filters_export = $filters;
			$lines[]        = self::csv_line( array( 'id', 'order_id', 'beneficiary_id', 'kind', 'tier_level', 'rate', 'amount', 'status', 'return_window_ends_at', 'created_at' ) );
			$page           = 1;
			do {
				$filters_export['page'] = $page;
				$data                   = self::commissions_report( $filters_export );
				foreach ( $data['rows'] as $row ) {
					$lines[] = self::csv_line( array(
						$row->id,
						$row->order_id,
						$row->beneficiary_id,
						$row->kind,
						$row->tier_level,
						$row->rate,
						$row->amount,
						$row->status,
						$row->return_window_ends_at,
						$row->created_at,
					) );
				}
				$fetched = count( $data['rows'] );
				$page++;
			} while ( $fetched === self::PAGE_SIZE && $page <= 40 );
		} elseif ( 'settlements' === $tab ) {
			$lines[] = self::csv_line( array( 'id', 'period_start', 'period_end', 'total_amount', 'status', 'approved_by', 'approved_at', 'created_at' ) );
			$page    = 1;
			do {
				$filters['page'] = $page;
				$data            = self::settlements_report( $filters );
				foreach ( $data['rows'] as $row ) {
					$lines[] = self::csv_line( array(
						$row->id,
						$row->period_start,
						$row->period_end,
						$row->total_amount,
						$row->status,
						$row->approved_by,
						$row->approved_at,
						$row->created_at,
					) );
				}
				$fetched = count( $data['rows'] );
				$page++;
			} while ( $fetched === self::PAGE_SIZE && $page <= 40 );
		} elseif ( 'withdrawals' === $tab ) {
			$lines[] = self::csv_line( array( 'id', 'affiliate_id', 'amount', 'status', 'iban', 'admin_note', 'requested_at', 'processed_at' ) );
			$page    = 1;
			do {
				$filters['page'] = $page;
				$data            = self::withdrawals_report( $filters );
				foreach ( $data['rows'] as $row ) {
					$lines[] = self::csv_line( array(
						$row->id,
						$row->affiliate_id,
						$row->amount,
						$row->status,
						$row->iban,
						$row->admin_note,
						$row->requested_at,
						$row->processed_at,
					) );
				}
				$fetched = count( $data['rows'] );
				$page++;
			} while ( $fetched === self::PAGE_SIZE && $page <= 40 );
		} elseif ( 'tree' === $tab ) {
			$lines[] = self::csv_line( array( 'affiliate_id', 'parent_id', 'staff_id', 'depth', 'path', 'user_id', 'type', 'status' ) );
			$root_id = ! empty( $filters['root_id'] ) ? (int) $filters['root_id'] : ( ! empty( $filters['affiliate_id'] ) ? (int) $filters['affiliate_id'] : 0 );
			$rows    = $root_id > 0 ? self::tree_subtree( $root_id, 2000 ) : self::tree_roots( 2000 );
			foreach ( $rows as $row ) {
				$lines[] = self::csv_line( array(
					$row->affiliate_id,
					isset( $row->parent_id ) ? $row->parent_id : '',
					isset( $row->staff_id ) ? $row->staff_id : '',
					$row->depth,
					$row->path,
					$row->user_id,
					$row->type,
					$row->status,
				) );
			}
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * @param array $cols
	 * @return string
	 */
	private static function csv_line( $cols ) {
		$out = array();
		foreach ( $cols as $col ) {
			$val = (string) $col;
			$val = str_replace( '"', '""', $val );
			if ( preg_match( '/[",\r\n]/', $val ) ) {
				$val = '"' . $val . '"';
			}
			$out[] = $val;
		}
		return implode( ',', $out );
	}
}
