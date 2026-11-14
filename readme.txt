=== Back In Stock Notifier Lite for WooCommerce ===
Contributors: hplugin
Tags: woocommerce, back in stock, stock notification, waitlist, email
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let customers subscribe to out-of-stock products and variations, then email them in controlled batches when stock returns.

== Description ==

**[Read the documentation](https://docs.wpallsolution.com/#/collection/bisn-plugin)** – installation, settings and answers, with screenshots. Need help? **[Contact support](https://support.wpallsolution.com/)**.

Back In Stock Notifier Lite adds a signup form to out-of-stock products and variations. When the product is available again, subscribers get an email with the product image, price and a button to the product page.

**Customer side**

* Signup form on out-of-stock simple products and on out-of-stock variations of variable products.
* Works with classic themes, block themes and a `[bisnl_form]` shortcode.
* AJAX signup without a page reload and an optional consent checkbox.
* One-click unsubscribe: the default email text contains an unsubscribe link, and every email also carries a List-Unsubscribe header. If you remove the `{unsubscribe_url}` placeholder from the email text, the visible link is gone (the header stays).
* Spam protection with a honeypot field and a rate limit of 10 signups per IP address per hour.

**Sending**

* Subscribers are queued when stock returns and emailed in batches through WP-Cron. You choose the batch size.
* A product-card email with image, price, sale discount and a button, sent through the WooCommerce mailer.
* Failed emails are retried up to three times. Each subscriber has a status: waiting, queued, sent, failed or unsubscribed.
* Also works when stock is managed on the parent product, and catches restocks made by imports or direct database updates.
* Edit the email subject, heading, button text and the message under the product card in Settings. The default message says that stock is limited and not reserved; if you replace that text, the wording is gone unless you write it yourself. The plugin never reserves stock for subscribers.

**Admin**

* WooCommerce > Back In Stock with Subscribers, Delivery log and Settings.
* Status filter, search, bulk delete, and a Send queued emails now button.
* CSV export of all subscribers (it ignores the status filter and search currently on screen).
* Optional email to the store owner on every new signup.

**Documentation**

Settings, the sending queue, the email, privacy and uninstall behaviour are explained in the [documentation](https://docs.wpallsolution.com/#/collection/bisn-plugin). Questions or ideas? [Contact support](https://support.wpallsolution.com/).

**Multilingual and multi-currency**

* WPML: the subscriber's language is saved, form and email texts are translatable in WPML String Translation, and each email is sent in the subscriber's language.
* Multi-currency: the price in the email is shown in the currency the subscriber used. CURCY and WPML's WooCommerce Multilingual are supported; other switchers can use the `bisnl_convert_price` filter.

== Privacy ==

The plugin stores the email address a visitor enters, the product, the visitor's language and currency, the signup date, and the delivery status. If the visitor is logged in, their user ID is stored too. It sends no data to any third-party service. Emails go through your site's mail setup.

The plugin registers with WordPress personal data export and erase tools. An export request for an email address lists that email's subscriptions with the product, status and subscription date; an erase request deletes all of that email address's subscription rows.

Deactivating keeps your data. Deleting the plugin always removes the subscribers table and the plugin's options; there is no setting to keep them, so export the CSV first if you want a copy.

== Frequently Asked Questions ==

= Does the notification reserve stock for the customer? =

No. The plugin never reserves stock. The default email text tells customers that stock is limited and not reserved; you can edit that text under WooCommerce > Back In Stock > Settings.

= How can I get support? =

Please [contact us here](https://support.wpallsolution.com/) for help, a new feature or a customization.

= Where is the documentation? =

[Read the documentation](https://docs.wpallsolution.com/#/collection/bisn-plugin) for the installation steps, every setting explained and screenshots.

= What happens to my data if I delete the plugin? =

The subscribers table and the plugin's options are removed permanently. Deactivating alone keeps them. Export the CSV first if you want to keep the list.

= How fast are emails sent? =

Emails go out in batches every five minutes through WP-Cron, or immediately from the Send queued emails now button. On low-traffic sites, trigger WP-Cron with a real server cron job.

= Does it work with variable products? =

Yes. Customers subscribe to a specific out-of-stock variation. Stock managed on the parent product is supported too.

= Stock changed but nobody was emailed. =

Changes made outside WooCommerce, such as imports or direct database updates, fire no stock event. A safety check runs with every queue run and emails subscribers whose product is in stock.

= Does it work with WPML? =

Yes. A subscription is tied to the product the customer is viewing, so each translated product keeps its own list. Form and email texts can be translated in WPML String Translation (context: back-in-stock-notifier-lite-for-woocommerce).

= Does it work with multi-currency stores? =

The subscriber's currency is saved at signup and the price in the email is shown in that currency. CURCY and WPML's WooCommerce Multilingual are supported. For other switchers use the `bisnl_convert_price` filter.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the Plugins screen, then activate it. WooCommerce is required.
2. Open WooCommerce > Back In Stock and review the Settings tab.
3. Mark a product as out of stock and open it on your store to see the signup form.

== Screenshots ==

1. Subscribers screen with status counts and the Send queued emails now button.
2. Signup form on an out-of-stock product.
3. The back-in-stock email with product image, price and button.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
