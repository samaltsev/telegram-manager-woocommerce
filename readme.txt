=== Telegram Control Center for WooCommerce ===
Contributors: phoenixgroup
Tags: telegram, woocommerce, order notifications, telegram notifications, ecommerce
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 2.1.1
License: GPLv2 or later

Reliable Telegram order notifications for WooCommerce stores. The plugin uses a queue, retries and delivery logs to make Telegram alerts dependable on production stores.

== Description ==

Telegram Control Center for WooCommerce helps store owners receive fast, reliable WooCommerce order notifications in Telegram.

Free version scope:

* New order notifications
* Order status change alerts
* Refund created alerts
* Compact Telegram templates: order, products, total, Open Order button
* Open Order button that links directly to the WooCommerce order page
* Queue-based delivery
* Retry after Telegram API errors
* Delivery logs
* System Info screen for troubleshooting

The Free version solves one task: notify the store owner about WooCommerce order events in Telegram. It does not include CRM, revenue reports, customer cards or Telegram-based order management.

Pro roadmap positioning:

* Telegram admin bot commands
* Daily and revenue reports
* Low stock alerts
* Customer cards
* Telegram-based order actions

These Pro features may exist in the codebase as a foundation, but they are not exposed as Free-version functionality.

== Installation ==

1. Upload and activate the plugin.
2. Open Telegram Control Center > Settings.
3. Add your Telegram bot token and Telegram chat ID.
4. Register the webhook or send a test message.
5. Configure notification types under Telegram Control Center > Notifications.

== Screens ==

The Free version includes:

* Dashboard
* Notifications
* Logs
* Settings
* System Info

== Changelog ==

= 2.1.1 =

* Cleaned Free production UI from Pro/Admin Bot/CRM wording.
* System Info now shows only Free database tables: jobs, job attempts and logs.
* Free schema no longer creates CRM or admin-bot Pro tables on new installs.
* Added Free feature gates for queue retry, delivery logs and system info.
* Updated dashboard checklist and notifications wording for a clearer Free product.

= 2.1.0 =

* Simplified Free admin menu to Dashboard, Notifications, Logs, Settings and System Info.
* Repositioned the Free version as Telegram order notifications for WooCommerce.
* Removed CRM, Orders and Reports pages from the Free menu.
* Removed payment/processing notifications from Free flow.
* Kept production-ready queue, retry and delivery log flow.

= 2.0.5 =

* Fixed Orders admin page crash when refunds were returned by WooCommerce order queries.

= 2.0.4 =

* Fixed Open Order button URL for HPOS and classic orders.

= 2.0.3 =

* Fixed early new-order notifications with empty product lists.
* Delays new-order notifications until WooCommerce line items are available.
* Updated Free status-change layout.
