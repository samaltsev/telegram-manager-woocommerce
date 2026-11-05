<?php
declare(strict_types=1);
namespace WCTM\Database;

if (!defined('ABSPATH')) {
    exit;
}

class LoggerRepository
{
    public function tableName()
    {
        global $wpdb;
        return $wpdb->prefix . 'wctm_logs';
    }

    public function insert(array $row)
    {
        global $wpdb;
        $level = sanitize_key($row['level'] ?? 'info');
        $context = sanitize_key($row['context'] ?? 'general');

        // In free mode keep DB lean: skip successful delivery logs unless explicitly enabled.
        if ($level === 'info' && $context === 'telegram_send' && !get_option('wctm_store_success_logs', false)) {
            return false;
        }

        $wpdb->insert($this->tableName(), array(
            'level' => $level,
            'context' => $context,
            'event' => sanitize_key($row['event'] ?? ''),
            'order_id' => !empty($row['order_id']) ? absint($row['order_id']) : null,
            'chat_id' => isset($row['chat_id']) ? sanitize_text_field((string) $row['chat_id']) : null,
            'request_payload' => $this->json($row['request_payload'] ?? null),
            'response_payload' => $this->json($row['response_payload'] ?? null),
            'message' => wp_strip_all_tags((string) ($row['message'] ?? '')),
            'created_at' => current_time('mysql'),
        ));
    }

    public function query(array $filters = array(), $limit = 100, $offset = 0)
    {
        global $wpdb;
        $where = array('1=1');
        $args = array();
        if (!empty($filters['context'])) {
            $where[] = 'context = %s';
            $args[] = sanitize_key($filters['context']);
        }
        if (!empty($filters['level'])) {
            $where[] = 'level = %s';
            $args[] = sanitize_key($filters['level']);
        }
        if (!empty($filters['exclude_level'])) {
            $where[] = 'level != %s';
            $args[] = sanitize_key($filters['exclude_level']);
        }
        if (!empty($filters['order_id'])) {
            $where[] = 'order_id = %d';
            $args[] = absint($filters['order_id']);
        }
        $sql = "SELECT * FROM {$this->tableName()} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $args[] = absint($limit);
        $args[] = absint($offset);
        return $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A);
    }

    public function stats()
    {
        global $wpdb;
        $table = $this->tableName();
        return array(
            'sent' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE level='info' AND context='telegram_send'"),
            'errors' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE level='error'"),
        );
    }

    public function clear()
    {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->tableName()}");
    }

    public function clearByLevel(string $level): int
    {
        global $wpdb;

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tableName()} WHERE level = %s",
                sanitize_key($level)
            )
        );
    }


    public function cleanupOld(int $retentionDays = 7): int
    {
        global $wpdb;

        $retentionDays = max(1, $retentionDays);
        $before = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($retentionDays * DAY_IN_SECONDS));

        return (int) $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->tableName()} WHERE created_at < %s",
                $before
            )
        );
    }

    public function countAll(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->tableName()}");
    }

    public function countByLevel(string $level): int
    {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->tableName()} WHERE level = %s",
                sanitize_key($level)
            )
        );
    }

    protected function json($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        return wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
