<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'bisnl_subscribers' );

delete_option( 'bisnl_settings' );
delete_option( 'bisnl_db_version' );
delete_option( 'bisnl_i18n_hash' );
wp_clear_scheduled_hook( 'bisnl_process_queue' );
