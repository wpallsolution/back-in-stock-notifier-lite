<?php
/**
 * Subscriber table access.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

// All queries below target the plugin's own table and need live data (queue state), so object caching is not used.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

class BISNL_DB {

	const DB_VERSION = '3';

	const STATUSES = array( 'waiting', 'queued', 'sent', 'failed', 'unsubscribed' );

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'bisnl_subscribers';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				email varchar(190) NOT NULL,
				product_id bigint(20) unsigned NOT NULL,
				variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				lang varchar(10) NOT NULL DEFAULT '',
				currency varchar(10) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'waiting',
				token varchar(64) NOT NULL,
				attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				created_at datetime NOT NULL,
				notified_at datetime NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY subscription (email,product_id,variation_id),
				KEY token (token),
				KEY status (status),
				KEY product (product_id,variation_id,status)
			) {$charset};"
		);

		update_option( 'bisnl_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'bisnl_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Create or re-arm a subscription.
	 *
	 * @return string 'created', 'exists' or 'error'.
	 */
	public static function subscribe( $email, $product_id, $variation_id, $user_id = 0, $lang = '', $currency = '' ) {
		global $wpdb;
		$table = self::table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, status FROM {$table} WHERE email = %s AND product_id = %d AND variation_id = %d", $email, $product_id, $variation_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( $row && in_array( $row->status, array( 'waiting', 'queued' ), true ) ) {
			return 'exists';
		}

		$token = wp_generate_password( 32, false );

		if ( $row ) {
			$ok = $wpdb->update(
				$table,
				array(
					'status'      => 'waiting',
					'token'       => $token,
					'attempts'    => 0,
					'last_error'  => null,
					'notified_at' => null,
					'user_id'     => $user_id,
					'lang'        => $lang,
					'currency'    => $currency,
					'created_at'  => current_time( 'mysql', true ),
				),
				array( 'id' => $row->id )
			);
		} else {
			$ok = $wpdb->insert(
				$table,
				array(
					'email'        => $email,
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'user_id'      => $user_id,
					'lang'         => $lang,
					'currency'     => $currency,
					'status'       => 'waiting',
					'token'        => $token,
					'created_at'   => current_time( 'mysql', true ),
				)
			);
		}

		return false === $ok ? 'error' : 'created';
	}

	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function get_by_token( $token ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s", $token ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function update_status( $id, $status, $extra = array() ) {
		global $wpdb;
		return $wpdb->update( self::table(), array_merge( array( 'status' => $status ), $extra ), array( 'id' => (int) $id ) );
	}

	/**
	 * Move waiting subscribers of a product/variation to the send queue.
	 *
	 * A parent-level event (variation_id 0) queues every variation of that product too: when
	 * stock is managed on the parent, variations never fire their own event. The queue
	 * processor re-checks each variation and puts still-unavailable ones back to waiting.
	 *
	 * @return int Rows queued.
	 */
	public static function queue_for( $product_id, $variation_id ) {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET status = 'queued' WHERE status = 'waiting' AND ( ( %d = 0 AND product_id = %d ) OR ( %d > 0 AND variation_id = %d ) )", $variation_id, $product_id, $variation_id, $variation_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return (int) $rows;
	}

	/**
	 * Distinct product/variation targets that still have waiting subscribers.
	 *
	 * @return object[] Rows with product_id and variation_id.
	 */
	public static function waiting_targets( $limit ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT product_id, variation_id FROM {$table} WHERE status = 'waiting' ORDER BY product_id ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function next_batch( $limit ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'queued' ORDER BY id ASC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function count_queued() {
		global $wpdb;
		$table = self::table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'queued'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function counts() {
		global $wpdb;
		$table  = self::table();
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = array_fill_keys( self::STATUSES, 0 );
		foreach ( $rows as $row ) {
			$counts[ $row->status ] = (int) $row->n;
		}
		return $counts;
	}

	/**
	 * @param array $args status (string|array), search, limit, offset.
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = self::table();
		list( $where, $params ) = self::build_where( $args );

		$limit  = isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : 20;
		$offset = isset( $args['offset'] ) ? max( 0, (int) $args['offset'] ) : 0;
		$order  = ! empty( $args['order_by_notified'] ) ? 'notified_at DESC, id DESC' : 'id DESC';

		$params[] = $limit;
		$params[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY {$order} LIMIT %d OFFSET %d", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}

	public static function count( $args = array() ) {
		global $wpdb;
		$table = self::table();
		list( $where, $params ) = self::build_where( $args );
		$sql = "SELECT COUNT(*) FROM {$table} {$where}";
		return (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_var( $sql ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function build_where( $args ) {
		global $wpdb;
		$clauses = array();
		$params  = array();

		if ( ! empty( $args['status'] ) ) {
			$statuses = array_values( array_intersect( (array) $args['status'], self::STATUSES ) );
			if ( $statuses ) {
				$clauses[] = 'status IN (' . implode( ',', array_fill( 0, count( $statuses ), '%s' ) ) . ')';
				$params    = array_merge( $params, $statuses );
			}
		}
		if ( ! empty( $args['search'] ) ) {
			$clauses[] = 'email LIKE %s';
			$params[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		return array( $clauses ? 'WHERE ' . implode( ' AND ', $clauses ) : '', $params );
	}

	public static function delete_ids( array $ids ) {
		global $wpdb;
		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return 0;
		}
		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	public static function delete_by_email( $email ) {
		global $wpdb;
		return (int) $wpdb->delete( self::table(), array( 'email' => $email ) );
	}

	public static function rows_by_email( $email ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
