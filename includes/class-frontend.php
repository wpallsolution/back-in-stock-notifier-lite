<?php
/**
 * Storefront form, AJAX signup and unsubscribe handling.
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

class BISNL_Frontend {

	private static $assets_needed = false;

	public static function init() {
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_classic' ), 31 );
		add_filter( 'render_block', array( __CLASS__, 'render_block_form' ), 10, 2 );
		add_shortcode( 'bisnl_form', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_ajax_bisnl_subscribe', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'wp_ajax_nopriv_bisnl_subscribe', array( __CLASS__, 'ajax_subscribe' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_unsubscribe' ) );
	}

	public static function register_assets() {
		wp_register_style( 'bisnl', BISNL_URL . 'assets/css/frontend.css', array(), BISNL_VERSION );
		wp_register_script( 'bisnl', BISNL_URL . 'assets/js/frontend.js', array( 'jquery' ), BISNL_VERSION, true );
		wp_localize_script(
			'bisnl',
			'bisnlData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'bisnl_subscribe' ),
				'error'   => __( 'Something went wrong. Please refresh the page and try again.', 'back-in-stock-notifier-lite-for-woocommerce' ),
			)
		);
	}

	/* ------------------------------------------------------------------ form */

	public static function render_classic() {
		global $product;
		if ( $product instanceof WC_Product ) {
			echo self::form_html( $product ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in form_html().
		}
	}

	/**
	 * Block themes do not fire the classic summary hooks, so append after the add-to-cart block.
	 */
	public static function render_block_form( $content, $block ) {
		if ( empty( $block['blockName'] ) || 'woocommerce/add-to-cart-form' !== $block['blockName'] || ! is_singular( 'product' ) ) {
			return $content;
		}
		$product = wc_get_product( get_queried_object_id() );
		return $product ? $content . self::form_html( $product ) : $content;
	}

	public static function shortcode( $atts ) {
		$atts    = shortcode_atts( array( 'product_id' => 0 ), $atts, 'bisnl_form' );
		$product = wc_get_product( absint( $atts['product_id'] ) ? absint( $atts['product_id'] ) : get_the_ID() );
		return $product ? self::form_html( $product ) : '';
	}

	/**
	 * Simple products render only when out of stock; variable products always render a hidden
	 * form that the script reveals when an out-of-stock variation is selected.
	 */
	public static function form_html( $product ) {
		static $rendered = array();

		$is_variable = $product->is_type( 'variable' );
		if ( ! $is_variable && ( $product->is_in_stock() || $product->is_on_backorder() ) ) {
			return '';
		}
		if ( ! $is_variable && ! $product->is_type( 'simple' ) ) {
			return '';
		}
		if ( isset( $rendered[ $product->get_id() ] ) ) {
			return '';
		}
		$rendered[ $product->get_id() ] = true;

		$settings = BISNL_Settings::all();
		$user     = wp_get_current_user();
		$email    = $user->exists() ? $user->user_email : '';

		wp_enqueue_style( 'bisnl' );
		wp_enqueue_script( 'bisnl' );

		ob_start();
		?>
		<div class="bisnl" data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-variable="<?php echo $is_variable ? '1' : '0'; ?>"<?php echo $is_variable ? ' hidden' : ''; ?>>
			<form class="bisnl__form" novalidate>
				<p class="bisnl__title"><?php echo esc_html( BISNL_I18n::translate( 'form_title', $settings['form_title'] ) ); ?></p>
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
				<input type="hidden" name="variation_id" value="0">
				<p class="bisnl__hp" aria-hidden="true"><input type="text" name="website" value="" tabindex="-1" autocomplete="off"></p>
				<label class="screen-reader-text" for="bisnl-email-<?php echo esc_attr( $product->get_id() ); ?>"><?php esc_html_e( 'Email address', 'back-in-stock-notifier-lite-for-woocommerce' ); ?></label>
				<input type="email" id="bisnl-email-<?php echo esc_attr( $product->get_id() ); ?>" name="email" class="bisnl__email" value="<?php echo esc_attr( $email ); ?>" placeholder="<?php esc_attr_e( 'Your email address', 'back-in-stock-notifier-lite-for-woocommerce' ); ?>" required>
				<?php if ( ! empty( $settings['consent_required'] ) ) : ?>
					<label class="bisnl__consent"><input type="checkbox" name="consent" value="1" required> <?php echo esc_html( BISNL_I18n::translate( 'consent_text', $settings['consent_text'] ) ); ?></label>
				<?php endif; ?>
				<button type="submit" class="button bisnl__button"><?php echo esc_html( BISNL_I18n::translate( 'button_text', $settings['button_text'] ) ); ?></button>
				<p class="bisnl__message" role="status" aria-live="polite"></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ ajax */

	public static function ajax_subscribe() {
		if ( ! check_ajax_referer( 'bisnl_subscribe', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Session expired. Please refresh the page and try again.', 'back-in-stock-notifier-lite-for-woocommerce' ) ), 403 );
		}

		// Honeypot: bots fill the hidden field. Pretend success.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => BISNL_Settings::get( 'success_text' ) ) );
		}

		if ( ! self::rate_limit_ok() ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests. Please try again later.', 'back-in-stock-notifier-lite-for-woocommerce' ) ), 429 );
		}

		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$product_id   = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$variation_id = isset( $_POST['variation_id'] ) ? absint( $_POST['variation_id'] ) : 0;

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
		}
		if ( BISNL_Settings::get( 'consent_required' ) && empty( $_POST['consent'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please tick the consent box.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
		}

		$parent = wc_get_product( $product_id );
		if ( ! $parent || 'publish' !== $parent->get_status() ) {
			wp_send_json_error( array( 'message' => __( 'Product not found.', 'back-in-stock-notifier-lite-for-woocommerce' ) ), 404 );
		}

		$target = $parent;
		if ( $variation_id ) {
			$target = wc_get_product( $variation_id );
			if ( ! $target || $target->get_parent_id() !== $product_id ) {
				wp_send_json_error( array( 'message' => __( 'Product not found.', 'back-in-stock-notifier-lite-for-woocommerce' ) ), 404 );
			}
		} elseif ( $parent->is_type( 'variable' ) ) {
			wp_send_json_error( array( 'message' => __( 'Please choose an option first.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
		}

		if ( $target->is_in_stock() || $target->is_on_backorder() ) {
			wp_send_json_error( array( 'message' => __( 'This product is currently available.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
		}

		$lang   = BISNL_I18n::product_language( $product_id );
		$result = BISNL_DB::subscribe( $email, $product_id, $variation_id, get_current_user_id(), $lang, BISNL_Currency::current() );

		if ( 'error' === $result ) {
			wp_send_json_error( array( 'message' => __( 'Could not save your request. Please try again.', 'back-in-stock-notifier-lite-for-woocommerce' ) ), 500 );
		}
		if ( 'exists' === $result ) {
			wp_send_json_success( array( 'message' => __( 'You are already subscribed to this product.', 'back-in-stock-notifier-lite-for-woocommerce' ) ) );
		}

		BISNL_Mailer::send_admin_notice( $email, $target );
		wp_send_json_success( array( 'message' => BISNL_I18n::translate( 'success_text', BISNL_Settings::get( 'success_text' ), $lang ) ) );
	}

	/** At most 10 signups per hour per IP. */
	private static function rate_limit_ok() {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'bisnl_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= 10 ) {
			return false;
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
		return true;
	}

	/* ----------------------------------------------------------- unsubscribe */

	public static function maybe_unsubscribe() {
		if ( empty( $_GET['bisnl_unsub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- one-click link authenticated by its secret token.
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['bisnl_unsub'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$row   = preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ? BISNL_DB::get_by_token( $token ) : null;

		if ( $row ) {
			BISNL_DB::update_status( $row->id, 'unsubscribed' );
			$message = __( 'You have been unsubscribed and will not receive further emails about this product.', 'back-in-stock-notifier-lite-for-woocommerce' );
		} else {
			$message = __( 'This unsubscribe link is invalid or has already been used.', 'back-in-stock-notifier-lite-for-woocommerce' );
		}

		wp_die( esc_html( $message ), esc_html__( 'Unsubscribe', 'back-in-stock-notifier-lite-for-woocommerce' ), array( 'response' => 200 ) );
	}
}
