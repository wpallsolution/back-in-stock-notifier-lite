<?php
/**
 * Multi-currency: remember the subscriber's currency and show the email price in it.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Currency {

	/** Currency the customer is shopping in right now (a currency switcher changes this). */
	public static function current() {
		return (string) get_woocommerce_currency();
	}

	public static function base() {
		return (string) get_option( 'woocommerce_currency' );
	}

	/**
	 * Convert a base-currency amount into $currency.
	 *
	 * Connect any other switcher with the `bisnl_convert_price` filter. CURCY and WPML's
	 * WCML are handled directly.
	 *
	 * @param float  $amount   Amount in the store base currency.
	 * @param string $currency Target currency code.
	 * @return float
	 */
	public static function convert( $amount, $currency ) {
		$amount = (float) $amount;
		if ( ! $currency || $currency === self::base() ) {
			return $amount;
		}

		$converted = (float) apply_filters( 'bisnl_convert_price', $amount, $currency );
		if ( $converted !== $amount ) {
			return $converted;
		}

		if ( has_filter( 'wcml_raw_price_amount' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WooCommerce Multilingual.
			return (float) apply_filters( 'wcml_raw_price_amount', $amount, $currency ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WooCommerce Multilingual.
		}

		// CURCY: read its rate directly, its wmc_get_price() does nothing inside the admin screens.
		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$list = WOOMULTI_CURRENCY_F_Data::get_ins()->get_list_currencies();
			if ( isset( $list[ $currency ]['rate'] ) ) {
				return $amount * (float) $list[ $currency ]['rate'];
			}
		}

		return $amount;
	}

	/** Formatted price in $currency (HTML), without re-running any switcher's own price filters. */
	public static function format( $amount, $currency ) {
		$currency = $currency ? $currency : self::base();
		$decimals = wc_get_price_decimals();

		return '<span class="woocommerce-Price-amount amount">' . esc_html(
			sprintf(
				get_woocommerce_price_format(),
				html_entity_decode( get_woocommerce_currency_symbol( $currency ), ENT_QUOTES, 'UTF-8' ),
				number_format( (float) $amount, $decimals, wc_get_price_decimal_separator(), wc_get_price_thousand_separator() )
			)
		) . '</span>';
	}
}
