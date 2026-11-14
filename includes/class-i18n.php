<?php
/**
 * WPML support: language of a subscription and translatable settings strings.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_I18n {

	const CONTEXT = 'back-in-stock-notifier-lite-for-woocommerce';
	const HASH    = 'bisnl_i18n_hash';

	/** Settings that customers or subscribers read. */
	const KEYS = array( 'form_title', 'button_text', 'success_text', 'consent_text', 'email_subject', 'email_heading', 'email_button', 'email_body' );

	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_register' ) );
	}

	public static function is_active() {
		return (bool) has_filter( 'wpml_translate_single_string' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
	}

	/** Register the editable texts with WPML String Translation when they change. */
	public static function maybe_register() {
		if ( ! self::is_active() ) {
			return;
		}
		$settings = BISNL_Settings::all();
		$values   = array_intersect_key( $settings, array_flip( self::KEYS ) );
		$hash     = md5( wp_json_encode( $values ) );
		if ( get_option( self::HASH ) === $hash ) {
			return;
		}
		foreach ( $values as $key => $value ) {
			do_action( 'wpml_register_single_string', self::CONTEXT, $key, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		}
		update_option( self::HASH, $hash, false );
	}

	/**
	 * Translate a setting into $lang, or into the current language when $lang is empty.
	 */
	public static function translate( $key, $value, $lang = '' ) {
		if ( ! self::is_active() ) {
			return $value;
		}

		$translated = apply_filters( 'wpml_translate_single_string', $value, self::CONTEXT, $key, $lang ? $lang : null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		if ( $translated !== $value ) {
			return $translated;
		}

		// The filter reads compiled translation files. Fall back to the stored translation so
		// a string saved but not yet compiled by String Translation still shows up.
		$target = $lang ? $lang : apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		if ( $target && $target !== apply_filters( 'wpml_default_language', null ) && function_exists( 'icl_get_string_id' ) && function_exists( 'icl_get_string_by_id' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
			$string_id = icl_get_string_id( $value, self::CONTEXT, $key );
			$stored    = $string_id ? icl_get_string_by_id( $string_id, $target ) : '';
			if ( is_string( $stored ) && '' !== $stored ) {
				return $stored;
			}
		}

		return $value;
	}

	/** Language code of a (parent) product, empty when WPML is not active. */
	public static function product_language( $product_id ) {
		if ( ! self::is_active() ) {
			return '';
		}
		$lang = apply_filters( 'wpml_element_language_code', null, array( 'element_id' => (int) $product_id, 'element_type' => 'product' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		return is_string( $lang ) ? $lang : '';
	}

	/**
	 * Run a callback with the front-end language switched to $lang.
	 *
	 * @return mixed Callback result.
	 */
	public static function in_language( $lang, $callback ) {
		if ( ! $lang || ! self::is_active() ) {
			return call_user_func( $callback );
		}
		$previous = apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		do_action( 'wpml_switch_language', $lang ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		try {
			return call_user_func( $callback );
		} finally {
			do_action( 'wpml_switch_language', $previous ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		}
	}
}
