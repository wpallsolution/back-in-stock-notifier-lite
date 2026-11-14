<?php
/**
 * Admin screens: subscribers, delivery log, settings, CSV export.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Admin {

	const SLUG     = 'bisnl';
	const PER_PAGE = 20;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_bisnl_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_bisnl_bulk', array( __CLASS__, 'bulk_action' ) );
		add_action( 'admin_post_bisnl_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_bisnl_send_now', array( __CLASS__, 'send_now' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( BISNL_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Back In Stock', 'back-in-stock-notifier-lite-for-woocommerce' ),
			__( 'Back In Stock', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url( array( 'tab' => 'settings' ) ) ) . '">' . esc_html__( 'Settings', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</a>' );
		return $links;
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'back-in-stock-notifier-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
	}

	/* ---------------------------------------------------------------- render */

	public static function render() {
		self::guard();

		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'subscribers'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tabs = array(
			'subscribers' => __( 'Subscribers', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'log'         => __( 'Delivery log', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'settings'    => __( 'Settings', 'back-in-stock-notifier-lite-for-woocommerce' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'subscribers';
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Back In Stock Notifier', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</h1>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Done.', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</p></div>';
		}

		self::render_counts();

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $key => $label ) {
			printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( self::url( array( 'tab' => $key ) ) ), $key === $tab ? ' nav-tab-active' : '', esc_html( $label ) );
		}
		echo '</nav>';

		if ( 'settings' === $tab ) {
			self::render_settings();
		} else {
			self::render_table( 'log' === $tab );
		}

		echo '</div>';
	}

	private static function render_counts() {
		$counts = BISNL_DB::counts();
		$labels = array(
			'waiting'      => __( 'Waiting', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'queued'       => __( 'Queued', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'sent'         => __( 'Sent', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'failed'       => __( 'Failed', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'unsubscribed' => __( 'Unsubscribed', 'back-in-stock-notifier-lite-for-woocommerce' ),
		);
		echo '<p class="bisnl-counts">';
		foreach ( $labels as $key => $label ) {
			printf( '<strong>%s:</strong> %d &nbsp; ', esc_html( $label ), (int) $counts[ $key ] );
		}
		if ( $counts['queued'] > 0 ) {
			$send = wp_nonce_url( admin_url( 'admin-post.php?action=bisnl_send_now' ), 'bisnl_send_now' );
			echo '<a class="button button-small" href="' . esc_url( $send ) . '">' . esc_html__( 'Send queued emails now', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</a>';
		}
		echo '</p>';
	}

	private static function render_table( $log ) {
		$tab    = $log ? 'log' : 'subscribers';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$allowed = $log ? array( 'sent', 'failed' ) : BISNL_DB::STATUSES;
		$filter  = in_array( $status, $allowed, true ) ? array( $status ) : ( $log ? $allowed : array() );

		$args  = array(
			'status'            => $filter,
			'search'            => $search,
			'limit'             => self::PER_PAGE,
			'offset'            => ( $paged - 1 ) * self::PER_PAGE,
			'order_by_notified' => $log,
		);
		$rows  = BISNL_DB::query( $args );
		$total = BISNL_DB::count( $args );

		// Filter form.
		echo '<form method="get" class="bisnl-filter" style="margin:1em 0">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::SLUG ) . '"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		echo '<select name="status"><option value="">' . esc_html__( 'All statuses', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</option>';
		foreach ( $allowed as $s ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $s ), selected( $status, $s, false ) );
		}
		echo '</select> <input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search email', 'back-in-stock-notifier-lite-for-woocommerce' ) . '"> ';
		submit_button( __( 'Filter', 'back-in-stock-notifier-lite-for-woocommerce' ), 'secondary', '', false );
		if ( ! $log ) {
			$export = wp_nonce_url( admin_url( 'admin-post.php?action=bisnl_export' ), 'bisnl_export' );
			echo ' <a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</a>';
		}
		echo '</form>';

		// Bulk form.
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bisnl_bulk">';
		wp_nonce_field( 'bisnl_bulk' );
		echo '<input type="hidden" name="return_tab" value="' . esc_attr( $tab ) . '">';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<td class="check-column"><input type="checkbox" onclick="jQuery(this).closest(\'table\').find(\'tbody .check-column input\').prop(\'checked\',this.checked)"></td>';
		$cols = $log
			? array( __( 'Email', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Product', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Status', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Sent at (UTC)', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Attempts', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Error', 'back-in-stock-notifier-lite-for-woocommerce' ) )
			: array( __( 'Email', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Product', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Status', 'back-in-stock-notifier-lite-for-woocommerce' ), __( 'Subscribed (UTC)', 'back-in-stock-notifier-lite-for-woocommerce' ) );
		foreach ( $cols as $c ) {
			echo '<th>' . esc_html( $c ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $rows ) {
			echo '<tr><td colspan="' . (int) ( count( $cols ) + 1 ) . '">' . esc_html__( 'Nothing here yet.', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</td></tr>';
		}
		foreach ( $rows as $row ) {
			$title = get_the_title( $row->variation_id ? $row->variation_id : $row->product_id );
			$link  = get_edit_post_link( $row->product_id );
			echo '<tr><th class="check-column"><input type="checkbox" name="ids[]" value="' . esc_attr( $row->id ) . '"></th>';
			echo '<td>' . esc_html( $row->email ) . '</td>';
			echo '<td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . '</td>';
			echo '<td>' . esc_html( $row->status ) . '</td>';
			if ( $log ) {
				echo '<td>' . esc_html( (string) $row->notified_at ) . '</td><td>' . (int) $row->attempts . '</td><td>' . esc_html( (string) $row->last_error ) . '</td>';
			} else {
				echo '<td>' . esc_html( $row->created_at ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<p><button type="submit" name="bulk" value="delete" class="button" onclick="return confirm(\'' . esc_js( __( 'Delete the selected rows?', 'back-in-stock-notifier-lite-for-woocommerce' ) ) . '\')">' . esc_html__( 'Delete selected', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</button></p>';
		echo '</form>';

		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post(
				paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			) . '</div></div>';
		}
	}

	private static function render_settings() {
		$s = BISNL_Settings::all();
		$o = BISNL_Settings::OPTION;

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="bisnl_save_settings">';
		wp_nonce_field( 'bisnl_save_settings' );
		echo '<table class="form-table" role="presentation">';

		self::text_row( $o, 'form_title', __( 'Form title', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['form_title'] );
		self::text_row( $o, 'button_text', __( 'Button text', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['button_text'] );
		self::text_row( $o, 'success_text', __( 'Success message', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['success_text'] );
		self::check_row( $o, 'consent_required', __( 'Require consent checkbox', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['consent_required'] );
		self::text_row( $o, 'consent_text', __( 'Consent text', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['consent_text'] );
		self::text_row( $o, 'batch_size', __( 'Emails per batch', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['batch_size'], __( 'Sent every five minutes via WP-Cron (1 to 200). Lower this on shared hosting.', 'back-in-stock-notifier-lite-for-woocommerce' ), 'number' );
		self::check_row( $o, 'admin_notify', __( 'Email me on new signups', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['admin_notify'] );
		self::text_row( $o, 'admin_email', __( 'Admin email', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['admin_email'], '', 'email' );
		self::text_row( $o, 'email_subject', __( 'Email subject', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['email_subject'], __( 'Placeholders: {product_name} {product_url} {unsubscribe_url} {site_name}', 'back-in-stock-notifier-lite-for-woocommerce' ) );
		self::text_row( $o, 'email_heading', __( 'Email heading', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['email_heading'] );
		self::text_row( $o, 'email_button', __( 'Product button text', 'back-in-stock-notifier-lite-for-woocommerce' ), $s['email_button'], __( 'The email shows a product card with image, price and this button. The text below appears under the card.', 'back-in-stock-notifier-lite-for-woocommerce' ) );

		echo '<tr><th scope="row"><label for="bisnl-body">' . esc_html__( 'Message under the product card', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</label></th><td>';
		echo '<textarea id="bisnl-body" name="' . esc_attr( $o ) . '[email_body]" rows="8" class="large-text code">' . esc_textarea( $s['email_body'] ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Keep {unsubscribe_url} in the body so customers can opt out.', 'back-in-stock-notifier-lite-for-woocommerce' ) . '</p></td></tr>';

		echo '</table>';
		submit_button();
		echo '</form>';
	}

	private static function text_row( $o, $key, $label, $value, $desc = '', $type = 'text' ) {
		printf(
			'<tr><th scope="row"><label for="bisnl-%1$s">%2$s</label></th><td><input type="%3$s" id="bisnl-%1$s" name="%4$s[%1$s]" value="%5$s" class="regular-text">%6$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $type ),
			esc_attr( $o ),
			esc_attr( $value ),
			$desc ? '<p class="description">' . esc_html( $desc ) . '</p>' : ''
		);
	}

	private static function check_row( $o, $key, $label, $value ) {
		printf(
			'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" name="%3$s[%1$s]" value="1"%4$s> %5$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $o ),
			checked( ! empty( $value ), true, false ),
			esc_html__( 'Enabled', 'back-in-stock-notifier-lite-for-woocommerce' )
		);
	}

	/* --------------------------------------------------------------- actions */

	public static function save_settings() {
		self::guard();
		check_admin_referer( 'bisnl_save_settings' );

		$input = isset( $_POST[ BISNL_Settings::OPTION ] ) && is_array( $_POST[ BISNL_Settings::OPTION ] ) ? wp_unslash( $_POST[ BISNL_Settings::OPTION ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in BISNL_Settings::sanitize().
		// sanitize() unslashes itself, so hand it slashed data.
		update_option( BISNL_Settings::OPTION, BISNL_Settings::sanitize( wp_slash( $input ) ) );

		wp_safe_redirect( self::url( array( 'tab' => 'settings', 'updated' => 1 ) ) );
		exit;
	}

	public static function bulk_action() {
		self::guard();
		check_admin_referer( 'bisnl_bulk' );

		$tab = isset( $_POST['return_tab'] ) && 'log' === $_POST['return_tab'] ? 'log' : 'subscribers';
		if ( isset( $_POST['bulk'] ) && 'delete' === $_POST['bulk'] && ! empty( $_POST['ids'] ) && is_array( $_POST['ids'] ) ) {
			BISNL_DB::delete_ids( array_map( 'absint', wp_unslash( $_POST['ids'] ) ) );
		}

		wp_safe_redirect( self::url( array( 'tab' => $tab, 'updated' => 1 ) ) );
		exit;
	}

	/** Run one batch immediately instead of waiting for the next WP-Cron tick. */
	public static function send_now() {
		self::guard();
		check_admin_referer( 'bisnl_send_now' );
		BISNL_Queue::process();
		wp_safe_redirect( self::url( array( 'updated' => 1 ) ) );
		exit;
	}

	public static function export_csv() {
		self::guard();
		check_admin_referer( 'bisnl_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="back-in-stock-subscribers-' . gmdate( 'Y-m-d' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $out, array( 'email', 'product_id', 'variation_id', 'product', 'status', 'subscribed_utc', 'notified_utc' ) );

		$offset = 0;
		do {
			$rows = BISNL_DB::query( array( 'limit' => 500, 'offset' => $offset ) );
			foreach ( $rows as $r ) {
				fputcsv(
					$out,
					array_map(
						array( __CLASS__, 'csv_safe' ),
						array( $r->email, $r->product_id, $r->variation_id, get_the_title( $r->variation_id ? $r->variation_id : $r->product_id ), $r->status, $r->created_at, (string) $r->notified_at )
					)
				);
			}
			$offset += 500;
		} while ( count( $rows ) === 500 );

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Neutralise spreadsheet formula injection. */
	public static function csv_safe( $value ) {
		$value = (string) $value;
		return preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
	}
}
