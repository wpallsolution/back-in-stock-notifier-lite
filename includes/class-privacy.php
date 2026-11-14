<?php
/**
 * WordPress personal data export and erase hooks.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Privacy {

	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function register_exporter( $exporters ) {
		$exporters['bisnl'] = array(
			'exporter_friendly_name' => __( 'Back in stock subscriptions', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['bisnl'] = array(
			'eraser_friendly_name' => __( 'Back in stock subscriptions', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	public static function export( $email ) {
		$items = array();
		foreach ( BISNL_DB::rows_by_email( $email ) as $row ) {
			$items[] = array(
				'group_id'    => 'bisnl',
				'group_label' => __( 'Back in stock subscriptions', 'back-in-stock-notifier-lite-for-woocommerce' ),
				'item_id'     => 'bisnl-' . $row->id,
				'data'        => array(
					array( 'name' => __( 'Product', 'back-in-stock-notifier-lite-for-woocommerce' ), 'value' => get_the_title( $row->variation_id ? $row->variation_id : $row->product_id ) ),
					array( 'name' => __( 'Status', 'back-in-stock-notifier-lite-for-woocommerce' ), 'value' => $row->status ),
					array( 'name' => __( 'Subscribed (UTC)', 'back-in-stock-notifier-lite-for-woocommerce' ), 'value' => $row->created_at ),
				),
			);
		}
		return array( 'data' => $items, 'done' => true );
	}

	public static function erase( $email ) {
		$removed = BISNL_DB::delete_by_email( $email );
		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
