=== Telegram Control Center for WooCommerce ===
Contributors: phoenixgroup
Tags: telegram, woocommerce, order notifications, telegram notifications, ecommerce
Requires at least: 6.0
Requires PHP: 8.0
Stable tag: 2.1.16
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

= 2.1.11 (Staging) =
* Added lease-owner checks for queue job completion and failure transitions.
* Added atomic webhook update claims and retry-safe cleanup.
* Added optional wp-config.php WCTM_TELEGRAM_BOT_TOKEN override.
* Restricted bot settings and webhook changes to administrators.
* Removed unused incomplete Action Scheduler prototype classes.


= 2.1.10 =

* Safety patch: preserve legacy CRM and marketing database tables during installation and upgrades.
* Remove Telegram message and response bodies from transport diagnostics.
* Improve queue lease compare-and-swap when recovering stale jobs.
* Synchronize package version metadata.


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

= 2.1.10 =

* Added configurable Telegram product stock status alerts: in stock, out of stock, backorder, low stock, quantity changes and publication state.
* Alerts for simple products and variations use the persistent message queue and logs.

= 2.1.10 =

* Independent status notifications for WooCommerce order statuses (including registered custom statuses).
* Payment-complete notifications with basic deduplication against processing status.
* New order, refund and product status notification handlers retained.

= 2.1.10 =

* Added separate formatting for payment-completed alerts.
* Added initial pending-order notification when enabled.
* Suppressed payment/processing duplicates in both event orders.
* Enabled core order-status switches by default for fresh installs (existing choices retained).

= 2.1.10 =

* Show all order-status, payment and product notification switches on the active Connection & Notifications screen.
* Keep existing order and stock event processing unchanged.

= 2.1.14 =

* Queue worker now receives a prompt non-blocking WP-Cron spawn request at shutdown.
* Keeps 5-minute periodic fallback and does not send Telegram messages during checkout.

= 2.1.14 =

* Removed the redundant initial pending message after a new order. Later genuine pending transitions are still notified.
* Suppressed the refunded status message when a fresh WooCommerce refund exists. Distinct partial refunds remain independent.
* Added refund-ID based duplicate protection.
* Preserved stock reduction/restoration messages and existing payment-status suppression.

= 2.1.16 =

* Added optional remaining stock in new-order Telegram notifications (off by default).
* Quantity shown only for products with WooCommerce-managed stock, including variations using parent stock.
* When enabled, the new-order notification is prepared at request shutdown so stock deductions made during checkout are reflected.

= 2.1.16 =

* Bundle order-related quantity changes into one notification; keep standalone adjustments separate.
* Correct async dispatch ordering when new-order stock details are enabled.
* Localized stock notifications and inline product buttons.
