<?php
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

foreach ([
    'wctm_settings',
    'wctm_db_version',
    'wctm_webhook_secret',
    'wctm_last_updates_snapshot',
    'wctm_last_cron_run',
    'wctm_last_webhook_hit',
    'wctm_queue_last_result',
    'wctm_queue_worker_lock',
] as $option) {
    delete_option($option);
    delete_transient($option);
}

foreach ([
    'wctm_logs',
    'wctm_job_attempts',
    'wctm_jobs',
    'wctm_admins',
    'wctm_notifications',
    'wctm_admin_bot_events',
    'wctm_customer_profile',
    'wctm_customer_tags',
    'wctm_customer_tag_links',
    'wctm_customer_activity',
] as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}");
}
