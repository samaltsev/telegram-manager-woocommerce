<?php
declare(strict_types=1);

namespace WCTM\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Free production schema.
 *
 * Free stores only the data required for reliable Telegram order delivery:
 * queue jobs, queue attempts and delivery logs. CRM, customer profiles,
 * admin-bot event history and reports are Pro-only runtime modules and are not
 * created by the Free installer.
 */
final class Schema
{
    public static function createTables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $jobs = $wpdb->prefix . 'wctm_jobs';
        $attempts = $wpdb->prefix . 'wctm_job_attempts';
        $logs = $wpdb->prefix . 'wctm_logs';

        dbDelta("CREATE TABLE {$jobs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(100) NOT NULL DEFAULT 'send_telegram_message',
            event VARCHAR(64) NOT NULL,
            order_id BIGINT UNSIGNED NULL,
            chat_id VARCHAR(64) NOT NULL,
            message_thread_id BIGINT UNSIGNED NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            priority INT NOT NULL DEFAULT 10,
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
            idempotency_key VARCHAR(191) NULL,
            trace_id VARCHAR(191) NULL,
            worker_id VARCHAR(191) NULL,
            available_at DATETIME NOT NULL,
            reserved_at DATETIME NULL,
            locked_at DATETIME NULL,
            last_error_code VARCHAR(100) NULL,
            last_error_message TEXT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status_available (status, available_at),
            KEY priority_id (priority, id),
            KEY order_id (order_id),
            KEY chat_id (chat_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$attempts} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_id BIGINT UNSIGNED NOT NULL,
            attempt_number SMALLINT UNSIGNED NOT NULL,
            status VARCHAR(20) NOT NULL,
            error_code VARCHAR(100) NULL,
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY job_attempt (job_id, attempt_number)
        ) {$charset};");

        dbDelta("CREATE TABLE {$logs} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            context VARCHAR(64) NOT NULL,
            event VARCHAR(64) NULL,
            order_id BIGINT UNSIGNED NULL,
            chat_id VARCHAR(64) NULL,
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            message TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY context (context),
            KEY order_id (order_id),
            KEY created_at (created_at)
        ) {$charset};");

        self::dropRemovedFreeTables();
    }

    private static function dropRemovedFreeTables(): void
    {
        global $wpdb;

        foreach ([
            'wctm_campaigns',
            'wctm_campaign_queue',
            'wctm_campaign_logs',
            'wctm_marketing_segments',
            'wctm_promo_digest',
            'wctm_marketing_campaigns',
            'wctm_marketing_campaign_recipients',
            'wctm_marketing_campaign_clicks',
            'wctm_marketing_consent_events',
            'wctm_segments',
            'wctm_subscribers',
            'wctm_subscribers_marketing_bot',
            'wctm_user_meta',
            'wctm_bonus_claims',
            'wctm_bonus_campaigns',
            'wctm_telegram_bind_tokens',
            'wctm_customer_bindings',
        ] as $suffix) {
            $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$suffix}");
        }
    }
}
