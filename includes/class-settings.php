<?php
/**
 * Plugin settings.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Settings {

	const OPTION = 'bisnl_settings';

	public static function defaults() {
		return array(
			'form_title'       => __( 'Email me when this is back in stock', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'button_text'      => __( 'Notify me', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'success_text'     => __( 'Thanks! We will email you when it is back in stock.', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'consent_required' => 0,
			'consent_text'     => __( 'I agree to receive one email about this product.', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'batch_size'       => 20,
			'admin_notify'     => 0,
			'admin_email'      => get_option( 'admin_email' ),
			'email_subject'    => __( '{product_name} is back in stock at {site_name}', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'email_heading'    => __( 'Good news, it is back!', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'email_button'     => __( 'Buy now', 'back-in-stock-notifier-lite-for-woocommerce' ),
			'email_body'       => __( '<p>Stock is limited and this email does not reserve an item for you.</p><p><a href="{unsubscribe_url}">Unsubscribe</a></p>', 'back-in-stock-notifier-lite-for-woocommerce' ),
		);
	}

	public static function all() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function sanitize( $input ) {
		$d = self::defaults();

		$admin_email = isset( $input['admin_email'] ) ? sanitize_email( wp_unslash( $input['admin_email'] ) ) : '';

		return array(
			'form_title'       => isset( $input['form_title'] ) ? sanitize_text_field( wp_unslash( $input['form_title'] ) ) : $d['form_title'],
			'button_text'      => isset( $input['button_text'] ) ? sanitize_text_field( wp_unslash( $input['button_text'] ) ) : $d['button_text'],
			'success_text'     => isset( $input['success_text'] ) ? sanitize_text_field( wp_unslash( $input['success_text'] ) ) : $d['success_text'],
			'consent_required' => empty( $input['consent_required'] ) ? 0 : 1,
			'consent_text'     => isset( $input['consent_text'] ) ? sanitize_text_field( wp_unslash( $input['consent_text'] ) ) : $d['consent_text'],
			'batch_size'       => isset( $input['batch_size'] ) ? min( 200, max( 1, absint( $input['batch_size'] ) ) ) : $d['batch_size'],
			'admin_notify'     => empty( $input['admin_notify'] ) ? 0 : 1,
			'admin_email'      => is_email( $admin_email ) ? $admin_email : $d['admin_email'],
			'email_subject'    => isset( $input['email_subject'] ) ? sanitize_text_field( wp_unslash( $input['email_subject'] ) ) : $d['email_subject'],
			'email_heading'    => isset( $input['email_heading'] ) ? sanitize_text_field( wp_unslash( $input['email_heading'] ) ) : $d['email_heading'],
			'email_button'     => isset( $input['email_button'] ) ? sanitize_text_field( wp_unslash( $input['email_button'] ) ) : $d['email_button'],
			'email_body'       => isset( $input['email_body'] ) ? wp_kses_post( wp_unslash( $input['email_body'] ) ) : $d['email_body'],
		);
	}
}
