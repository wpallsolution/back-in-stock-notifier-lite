<?php
/**
 * Stock change detection and batched sending.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Queue {

	const CRON_HOOK  = 'bisnl_process_queue';
	const CRON_SLUG  = 'bisnl_five_minutes';
	const MAX_TRIES  = 3;

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_product_stock_status' ), 10, 2 );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_variation_stock_status' ), 10, 3 );
		add_action( self::CRON_HOOK, array( __CLASS__, 'process' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, self::CRON_SLUG, self::CRON_HOOK );
		}
	}

	public static function add_schedule( $schedules ) {
		$schedules[ self::CRON_SLUG ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every five minutes', 'back-in-stock-notifier-lite-for-woocommerce' ),
		);
		return $schedules;
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function on_product_stock_status( $product_id, $status ) {
		if ( 'instock' === $status ) {
			self::enqueue( (int) $product_id, 0 );
		}
	}

	public static function on_variation_stock_status( $variation_id, $status, $product = null ) {
		if ( 'instock' !== $status ) {
			return;
		}
		$parent = $product instanceof WC_Product ? $product->get_parent_id() : wp_get_post_parent_id( $variation_id );
		self::enqueue( (int) $parent, (int) $variation_id );
	}

	private static function enqueue( $product_id, $variation_id ) {
		if ( BISNL_DB::queue_for( $product_id, $variation_id ) > 0 ) {
			// Start the first batch soon instead of waiting for the next cron tick.
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		}
	}

	/**
	 * Safety net for restocks that fire no WooCommerce stock-status event, such as imports,
	 * direct database updates or third-party tools: queue waiting subscribers whose
	 * product or variation is in stock now.
	 */
	public static function reconcile() {
		foreach ( BISNL_DB::waiting_targets( 100 ) as $target ) {
			$product = wc_get_product( $target->variation_id ? $target->variation_id : $target->product_id );
			if ( $product && $product->is_in_stock() ) {
				BISNL_DB::queue_for( (int) $target->product_id, (int) $target->variation_id );
			}
		}
	}

	/**
	 * Send one batch. If more rows remain, a follow-up run is scheduled.
	 */
	public static function process() {
		self::reconcile();

		$limit = (int) BISNL_Settings::get( 'batch_size' );
		$rows  = BISNL_DB::next_batch( $limit );

		foreach ( $rows as $row ) {
			$product = wc_get_product( $row->variation_id ? $row->variation_id : $row->product_id );

			if ( ! $product || 'publish' !== get_post_status( $row->product_id ) ) {
				BISNL_DB::update_status( $row->id, 'failed', array( 'last_error' => __( 'Product no longer available.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
				continue;
			}

			// Stock went away again before this email was sent: wait for the next restock.
			if ( ! $product->is_in_stock() ) {
				BISNL_DB::update_status( $row->id, 'waiting' );
				continue;
			}

			$result = BISNL_Mailer::send_back_in_stock( $row, $product );

			if ( true === $result ) {
				BISNL_DB::update_status(
					$row->id,
					'sent',
					array(
						'notified_at' => current_time( 'mysql', true ),
						'last_error'  => null,
					)
				);
				continue;
			}

			$attempts = (int) $row->attempts + 1;
			BISNL_DB::update_status(
				$row->id,
				$attempts >= self::MAX_TRIES ? 'failed' : 'queued',
				array(
					'attempts'   => $attempts,
					'last_error' => $result->get_error_message(),
				)
			);
		}

		if ( BISNL_DB::count_queued() > 0 && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 60, self::CRON_HOOK );
		}
	}
}
