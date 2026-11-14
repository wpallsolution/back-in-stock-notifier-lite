<?php
/**
 * Builds and sends notification emails through the WooCommerce mailer.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Mailer {

	public static function unsubscribe_url( $token ) {
		return add_query_arg( 'bisnl_unsub', rawurlencode( $token ), home_url( '/' ) );
	}

	private static function replace( $text, $product, $row, $escape_url ) {
		$url = $product->get_permalink();
		$map = array(
			'{product_name}'    => $product->get_name(),
			'{product_url}'     => $escape_url ? esc_url( $url ) : $url,
			'{unsubscribe_url}' => $escape_url ? esc_url( self::unsubscribe_url( $row->token ) ) : self::unsubscribe_url( $row->token ),
			'{site_name}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
		if ( $escape_url ) {
			$map['{product_name}'] = esc_html( $map['{product_name}'] );
			$map['{site_name}']    = esc_html( $map['{site_name}'] );
		}
		return strtr( $text, $map );
	}

	/**
	 * Product card in a table layout with inline styles, so it renders in email clients.
	 * Every dynamic value is escaped here.
	 */
	public static function card_html( $product, $row, $button_text ) {
		$image_id = $product->get_image_id();
		if ( ! $image_id && $product->get_parent_id() ) {
			$parent   = wc_get_product( $product->get_parent_id() );
			$image_id = $parent ? $parent->get_image_id() : 0;
		}
		$image = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_single' ) : '';
		$image = $image ? $image : wc_placeholder_img_src( 'woocommerce_single' );

		$name    = esc_html( $product->get_name() );
		$details = $product->is_type( 'variation' ) ? wc_get_formatted_variation( $product, true, false ) : '';

		// Prices are stored in the base currency; show them in the currency the subscriber used.
		$currency = ! empty( $row->currency ) ? $row->currency : BISNL_Currency::base();
		$regular  = (float) $product->get_regular_price();
		$current  = (float) $product->get_price();
		$price    = '<span style="font-size:22px;font-weight:bold;color:#212121;">' . BISNL_Currency::format( BISNL_Currency::convert( $current, $currency ), $currency ) . '</span>';
		if ( $product->is_on_sale() && $regular > $current && $regular > 0 ) {
			$off    = (int) round( ( $regular - $current ) / $regular * 100 );
			$price .= ' <span style="font-size:14px;color:#878787;text-decoration:line-through;">' . BISNL_Currency::format( BISNL_Currency::convert( $regular, $currency ), $currency ) . '</span>';
			/* translators: %d: discount percentage */
			$price .= ' <span style="font-size:14px;font-weight:bold;color:#388e3c;">' . esc_html( sprintf( __( '%d%% off', 'back-in-stock-notifier-lite-for-woocommerce' ), $off ) ) . '</span>';
		}

		$url = esc_url( $product->get_permalink() );

		ob_start();
		?>
		<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid #e0e0e0;border-radius:4px;background:#ffffff;margin:0 0 20px;">
			<tr>
				<td width="40%" align="center" valign="middle" style="padding:16px;background:#ffffff;">
					<a href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" width="180" style="display:block;max-width:100%;height:auto;border:0;"></a>
				</td>
				<td width="60%" valign="top" style="padding:16px 16px 16px 0;font-family:Arial,Helvetica,sans-serif;">
					<span style="display:inline-block;background:#388e3c;color:#ffffff;font-size:12px;font-weight:bold;padding:3px 8px;border-radius:2px;"><?php esc_html_e( 'BACK IN STOCK', 'back-in-stock-notifier-lite-for-woocommerce' ); ?></span>
					<p style="margin:10px 0 4px;font-size:18px;line-height:24px;color:#212121;"><a href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="color:#212121;text-decoration:none;"><?php echo $name; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a></p>
					<?php if ( $details ) : ?>
						<p style="margin:0 0 6px;font-size:13px;color:#878787;"><?php echo wp_kses_post( $details ); ?></p>
					<?php endif; ?>
					<p style="margin:8px 0 14px;"><?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above. ?></p>
					<a href="<?php echo $url; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" style="display:inline-block;background:#fb641b;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;padding:12px 28px;border-radius:2px;"><?php echo esc_html( $button_text ); ?></a>
				</td>
			</tr>
		</table>
		<?php
		return ob_get_clean();
	}

	/**
	 * @param object      $row     Subscriber row.
	 * @param WC_Product  $product Product or variation.
	 * @return true|WP_Error
	 */
	public static function send_back_in_stock( $row, $product ) {
		$lang = isset( $row->lang ) ? (string) $row->lang : '';
		return BISNL_I18n::in_language( $lang, static function () use ( $row, $product, $lang ) {
			return BISNL_Mailer::build_and_send( $row, $product, $lang );
		} );
	}

	public static function build_and_send( $row, $product, $lang ) {
		$settings = BISNL_Settings::all();
		foreach ( array( 'email_subject', 'email_heading', 'email_button', 'email_body' ) as $key ) {
			$settings[ $key ] = BISNL_I18n::translate( $key, $settings[ $key ], $lang );
		}

		$subject = self::replace( $settings['email_subject'], $product, $row, false );
		$heading = self::replace( $settings['email_heading'], $product, $row, false );
		$body    = self::replace( $settings['email_body'], $product, $row, true );

		$mailer  = WC()->mailer();
		$card    = self::card_html( $product, $row, $settings['email_button'] );
		$message = $mailer->wrap_message( $heading, $card . wpautop( wp_kses_post( $body ) ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'List-Unsubscribe: <' . self::unsubscribe_url( $row->token ) . '>',
		);

		$sent = $mailer->send( $row->email, $subject, $message, $headers );

		return $sent ? true : new WP_Error( 'bisnl_mail', __( 'wp_mail() returned false.', 'back-in-stock-notifier-lite-for-woocommerce' ) );
	}

	public static function send_admin_notice( $email, $product ) {
		$settings = BISNL_Settings::all();
		if ( empty( $settings['admin_notify'] ) || ! is_email( $settings['admin_email'] ) ) {
			return;
		}
		wp_mail(
			$settings['admin_email'],
			/* translators: %s: product name */
			sprintf( __( 'New back-in-stock signup: %s', 'back-in-stock-notifier-lite-for-woocommerce' ), $product->get_name() ),
			/* translators: 1: email, 2: product name */
			sprintf( __( '%1$s subscribed to be notified when "%2$s" is back in stock.', 'back-in-stock-notifier-lite-for-woocommerce' ), $email, $product->get_name() )
		);
	}
}
