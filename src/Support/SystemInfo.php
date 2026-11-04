<?php
declare(strict_types=1);

namespace WCTM\Support;

use WCTM\Core\WooCommerceCheck;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class SystemInfo
{
    public function __construct(
        private BotSettingsRepository $bots,
        private DbQueueRepository $queueRepo
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function data(): array
    {
        global $wpdb;

        $tableNames = [
            'jobs' => $wpdb->prefix . 'wctm_jobs',
            'job_attempts' => $wpdb->prefix . 'wctm_job_attempts',
            'logs' => $wpdb->prefix . 'wctm_logs',
        ];

        $tables = [];
        foreach ($tableNames as $key => $tableName) {
            $tables[$key] = [
                'label' => $this->tableLabel($key),
                'name' => $tableName,
                'exists' => $this->tableExists($tableName),
                'rows' => null,
            ];

            if ($tables[$key]['exists']) {
                $tables[$key]['rows'] = $this->tableRows($tableName);
            }
        }

        $queueStats = $this->safeQueueStats();
        $settings = (array) get_option('wctm_settings', []);

        $botToken = trim((string) ($settings['bot_token'] ?? $this->bots->get('bot_token', '')));
        $chatId = trim((string) ($settings['admin_chat_id'] ?? $this->bots->get('admin_chat_id', '')));
        $botUsername = trim((string) ($settings['bot_username'] ?? $this->bots->get('bot_username', '')));
        $botName = trim((string) ($settings['bot_name'] ?? $this->bots->get('bot_name', '')));

        $lastLog = $this->lastLog($tableNames['logs'], $tables['logs']['exists']);
        $lastError = $this->lastError($tableNames['logs'], $tables['logs']['exists']);

        $lastNotification = (string) ($queueStats['last_done_at'] ?? '');
        if ($lastNotification === '' && !empty($lastLog['created_at'])) {
            $lastNotification = (string) $lastLog['created_at'];
        }

        return [
            'plugin' => [
                'name' => 'Telegram Control Center',
                'version' => defined('WCTM_VERSION') ? (string) WCTM_VERSION : 'n/a',
                'db_version' => (string) get_option('wctm_db_version', 'n/a'),
            ],
            'environment' => [
                'wp_version' => (string) get_bloginfo('version'),
                'woo_active' => WooCommerceCheck::isActive(),
                'woo_version' => defined('WC_VERSION') ? (string) WC_VERSION : 'not active',
                'php_version' => PHP_VERSION,
                'memory_limit' => (string) ini_get('memory_limit'),
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG,
            ],
            'telegram' => [
                'bot_token_configured' => $botToken !== '',
                'chat_configured' => $chatId !== '',
                'chat_id' => $chatId,
                'bot_username' => $botUsername,
                'bot_name' => $botName,
                'last_notification' => $lastNotification,
                'last_error' => !empty($lastError['message']) ? (string) $lastError['message'] : '',
                'last_error_at' => !empty($lastError['created_at']) ? (string) $lastError['created_at'] : '',
            ],
            'tables' => $tables,
            'queue' => $queueStats,
            'queue_health' => [
                'cron_next' => wp_next_scheduled('wctm_process_queue') ? wp_date('Y-m-d H:i:s', (int) wp_next_scheduled('wctm_process_queue')) : 'not scheduled',
                'last_cron_run' => (string) get_option('wctm_last_cron_run', 'never'),
                'last_result' => json_decode((string) get_option('wctm_queue_last_result', ''), true) ?: [],
            ],

            // Backward-compatible flat keys for older views.
            'plugin_version' => defined('WCTM_VERSION') ? (string) WCTM_VERSION : 'n/a',
            'db_version' => (string) get_option('wctm_db_version', 'n/a'),
            'wp_version' => (string) get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'woo_active' => WooCommerceCheck::isActive() ? 'yes' : 'no',
            'woo_version' => defined('WC_VERSION') ? (string) WC_VERSION : 'not active',
            'memory_limit' => (string) ini_get('memory_limit'),
        ];
    }

    private function tableLabel(string $key): string
    {
        return match ($key) {
            'jobs' => __('Jobs table', 'telegram-manager-woocommerce'),
            'job_attempts' => __('Job attempts table', 'telegram-manager-woocommerce'),
            'logs' => __('Logs table', 'telegram-manager-woocommerce'),
            default => $key,
        };
    }

    private function tableExists(string $tableName): bool
    {
        global $wpdb;

        $like = $wpdb->esc_like($tableName);
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $like));

        return is_string($found) && strtolower($found) === strtolower($tableName);
    }

    private function tableRows(string $tableName): int
    {
        global $wpdb;

        // Table names are controlled internally and cannot be used with placeholders.
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$tableName}`");
    }

    /**
     * @return array<string,mixed>
     */
    private function safeQueueStats(): array
    {
        try {
            return (array) $this->queueRepo->stats();
        } catch (\Throwable $e) {
            return [
                'pending' => 0,
                'processing' => 0,
                'retry' => 0,
                'failed' => 0,
                'stale_processing' => 0,
                'last_failed_attempt_at' => '',
                'last_done_at' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function lastLog(string $logsTable, bool $tableExists): array
    {
        global $wpdb;

        if (!$tableExists) {
            return [];
        }

        $row = $wpdb->get_row("SELECT * FROM `{$logsTable}` ORDER BY id DESC LIMIT 1", ARRAY_A);

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function lastError(string $logsTable, bool $tableExists): array
    {
        global $wpdb;

        if (!$tableExists) {
            return [];
        }

        $row = $wpdb->get_row(
            "SELECT * FROM `{$logsTable}` WHERE level IN ('error', 'failed') ORDER BY id DESC LIMIT 1",
            ARRAY_A
        );

        return is_array($row) ? $row : [];
    }
}
