<?php
declare(strict_types=1);

namespace WCTM\CRM;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerActivityRepository
{
    public function record(int $userId, string $eventType, string $objectType = '', int $objectId = 0, array $meta = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wctm_customer_activity';
        $wpdb->insert($table, [
            'user_id' => max(0, $userId),
            'telegram_id' => sanitize_text_field((string) ($meta['telegram_id'] ?? '')),
            'event_type' => sanitize_key($eventType),
            'object_type' => sanitize_key($objectType),
            'object_id' => max(0, $objectId),
            'meta_json' => wp_json_encode($meta),
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public function recentForUser(int $userId, int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wctm_customer_activity';
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d",
            $userId,
            max(1, min(200, $limit))
        ), ARRAY_A);
    }
}
