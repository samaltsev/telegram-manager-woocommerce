<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminBotSubscriberRepository implements AdminBotSubscriberRepositoryInterface
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'wctm_admins';
    }

    public function addSubscriber(string $chatId, ?string $username = null, array $meta = []): bool
    {
        global $wpdb;
        $chatId = trim($chatId);
        if ($chatId === '') {
            return false;
        }
        $data = [
            'telegram_user_id' => $chatId,
            'telegram_username' => trim((string) $username),
            'status' => 'subscribed',
            'created_at' => current_time('mysql'),
        ];
        $result = $wpdb->replace($this->table, array_merge($data, $meta));
        if ($result === false) {
            Logger::log('error', 'admin_bot_repo', 'Failed to save admin chat ID', ['wpdb_last_error' => $wpdb->last_error]);
        }
        return $result !== false;
    }

    public function updateStatusByChatId(string $chatId, string $status): bool
    {
        global $wpdb;
        return $wpdb->update($this->table, ['status' => $status], ['telegram_user_id' => trim($chatId)]) !== false;
    }

    public function findByChatId(string $chatId): ?AdminBotSubscriber
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE telegram_user_id = %s LIMIT 1", trim($chatId)), ARRAY_A);
        return is_array($row) ? $this->map($row) : null;
    }

    public function all(string $search = ''): array
    {
        global $wpdb;
        $sql = "SELECT * FROM {$this->table}";
        if ($search !== '') {
            $sql .= $wpdb->prepare(' WHERE telegram_user_id LIKE %s', '%' . $wpdb->esc_like($search) . '%');
        }
        $sql .= ' ORDER BY id ASC';
        return array_map(fn(array $row) => $this->map($row), (array) $wpdb->get_results($sql, ARRAY_A));
    }

    private function map(array $row): AdminBotSubscriber
    {
        return new AdminBotSubscriber(
            (int) ($row['id'] ?? 0),
            (string) ($row['telegram_user_id'] ?? ''),
            (string) ($row['telegram_username'] ?? ''),
            (string) ($row['status'] ?? 'active')
        );
    }
}
