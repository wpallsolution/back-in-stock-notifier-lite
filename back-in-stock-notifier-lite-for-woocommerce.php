<?php
/**
 * Plugin Name:       Back In Stock Notifier Lite for WooCommerce
 * Description:       Let customers subscribe to out-of-stock products and variations, then email them in controlled batches when stock returns. Includes AJAX signup, unsubscribe links, delivery log and CSV export.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            hplugin
 * Author URI:        https://profiles.wordpress.org/hplugin/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       back-in-stock-notifier-lite-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package Back_In_Stock_Notifier_Lite
 */

defined( 'ABSPATH' ) || exit;

define( 'BISNL_VERSION', '1.0.0' );
define( 'BISNL_MIN_WC', '8.0' );
define( 'BISNL_FILE', __FILE__ );
define( 'BISNL_PATH', plugin_dir_path( __FILE__ ) );
define( 'BISNL_URL', plugin_dir_url( __FILE__ ) );

require_once BISNL_PATH . 'includes/class-db.php';
require_once BISNL_PATH . 'includes/class-settings.php';
require_once BISNL_PATH . 'includes/class-i18n.php';
require_once BISNL_PATH . 'includes/class-currency.php';
require_once BISNL_PATH . 'includes/class-mailer.php';
require_once BISNL_PATH . 'includes/class-queue.php';
require_once BISNL_PATH . 'includes/class-frontend.php';
require_once BISNL_PATH . 'includes/class-privacy.php';
require_once BISNL_PATH . 'admin/class-admin.php';

register_activation_hook( __FILE__, array( 'BISNL_DB', 'install' ) );
register_deactivation_hook( __FILE__, array( 'BISNL_Queue', 'unschedule' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', BISNL_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', BISNL_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || version_compare( WC_VERSION, BISNL_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: minimum WooCommerce version */
								__( 'Back In Stock Notifier Lite requires WooCommerce %s or newer.', 'back-in-stock-notifier-lite-for-woocommerce' ),
								BISNL_MIN_WC
							)
						)
					);
				}
			);
			return;
		}

		BISNL_DB::maybe_upgrade();
		BISNL_I18n::init();
		BISNL_Queue::init();
		BISNL_Frontend::init();
		BISNL_Privacy::init();

		if ( is_admin() ) {
			BISNL_Admin::init();
		}
	}
);
