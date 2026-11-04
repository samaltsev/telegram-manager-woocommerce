<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Database\LoggerRepository;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class DashboardPageController
{
    public function __construct(
        private LoggerRepository $logs,
        private DbQueueRepository $queue
    ) {
    }

    public function render(): void
    {
        $this->guard();

        $queue = $this->queue->stats();
        $settings = (array) get_option('wctm_settings', []);
        $hasToken = trim((string) ($settings['bot_token'] ?? '')) !== '';
        $hasChatId = trim((string) ($settings['admin_chat_id'] ?? '')) !== '';
        $configured = $hasToken && $hasChatId;

        $failed = (int) ($queue['failed'] ?? 0);
        $pending = (int) ($queue['pending'] ?? 0);
        $processing = (int) ($queue['processing'] ?? 0);
        $retry = (int) ($queue['retry'] ?? 0);
        $stale = (int) ($queue['stale_processing'] ?? 0);
        $lastNotification = (string) ($queue['last_done_at'] ?? '');
        $queueHealthy = $failed === 0 && $stale === 0;
        $busyTotal = $pending + $processing + $retry + $failed;
        $queueProgress = $busyTotal === 0 ? 100 : min(100, (int) round((($pending + $processing + $retry) / max(1, $busyTotal)) * 100));

        $recent = [];
        foreach ((array) $this->queue->recentAttempts(5) as $item) {
            $event = (string) ($item['event'] ?? 'telegram_send');
            $orderId = !empty($item['order_id']) ? '#' . (int) $item['order_id'] : '-';
            $recent[] = [
                'status' => sanitize_key((string) ($item['status'] ?? 'info')),
                'title' => $this->eventLabel($event) . ' ' . $orderId,
                'created_at' => (string) ($item['created_at'] ?? ''),
            ];
        }

        ViewRenderer::render('dashboard', [
            'title' => __('Telegram Control Center', 'telegram-manager-woocommerce'),
            'subtitle' => __('Reliable Telegram order notifications with queue, retry and logs.', 'telegram-manager-woocommerce'),
            'badge' => __('Free', 'telegram-manager-woocommerce'),
            'actions' => [
                ['type' => 'text', 'label' => 'v' . (defined('WCTM_VERSION') ? WCTM_VERSION : '')],
                ['label' => __('Documentation', 'telegram-manager-woocommerce'), 'url' => 'https://core.telegram.org/bots#how-do-i-create-a-bot', 'class' => 'tcc-link', 'external' => true],
                ['label' => __('Configure', 'telegram-manager-woocommerce'), 'url' => admin_url('admin.php?page=wctm-settings'), 'class' => 'tcc-btn tcc-btn-primary'],
                ['label' => __('Open Logs', 'telegram-manager-woocommerce'), 'url' => admin_url('admin.php?page=wctm-logs'), 'class' => 'tcc-btn tcc-btn-secondary'],
            ],
            'configured' => $configured,
            'has_token' => $hasToken,
            'has_chat_id' => $hasChatId,
            'has_last_test' => (string) get_option('wctm_last_test_message', '') !== '',
            'bot_status_label' => $configured ? __('Connected', 'telegram-manager-woocommerce') : __('Setup required', 'telegram-manager-woocommerce'),
            'chat_label' => $hasChatId ? $this->maskChatId((string) ($settings['admin_chat_id'] ?? '')) : __('Not configured', 'telegram-manager-woocommerce'),
            'queue_status_label' => $queueHealthy ? __('Healthy', 'telegram-manager-woocommerce') : __('Needs attention', 'telegram-manager-woocommerce'),
            'queue_healthy' => $queueHealthy,
            'last_message_human' => $this->humanDate($lastNotification),
            'has_last_message' => $lastNotification !== '',
            'notifications_today' => $this->notificationsToday(),
            'last_notification_label' => $lastNotification !== '' ? $lastNotification : '-',
            'failed' => $failed,
            'queue_progress' => $queueProgress,
            'queue_metrics' => [
                ['label' => __('Pending', 'telegram-manager-woocommerce'), 'value' => (string) $pending],
                ['label' => __('Processing', 'telegram-manager-woocommerce'), 'value' => (string) $processing],
                ['label' => __('Retry', 'telegram-manager-woocommerce'), 'value' => (string) $retry],
                ['label' => __('Stale processing', 'telegram-manager-woocommerce'), 'value' => (string) $stale],
                ['label' => __('Oldest pending', 'telegram-manager-woocommerce'), 'value' => (string) ($queue['oldest_pending_at'] ?? '-')],
                ['label' => __('Last failed', 'telegram-manager-woocommerce'), 'value' => (string) ($queue['last_failed_attempt_at'] ?? '-')],
            ],
            'recent_activity' => $recent,
            'bot_image' => defined('WCTM_URL') ? WCTM_URL . 'assets/admin/img/bot.png' : '',
        ]);
    }

    public function renderReports(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=wctm-dashboard'));
        exit;
    }

    private function notificationsToday(): int
    {
        global $wpdb;
        $table = $this->queue->tableName();
        $today = wp_date('Y-m-d 00:00:00', current_time('timestamp'));
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = %s AND updated_at >= %s", 'done', $today));
    }

    private function humanDate(string $mysql): string
    {
        if ($mysql === '' || $mysql === '-') {
            return __('No messages yet', 'telegram-manager-woocommerce');
        }
        $timestamp = strtotime($mysql);
        return $timestamp ? human_time_diff($timestamp, current_time('timestamp')) . ' ' . __('ago', 'telegram-manager-woocommerce') : $mysql;
    }

    private function maskChatId(string $chatId): string
    {
        $chatId = trim($chatId);
        if ($chatId === '' || strlen($chatId) <= 6) {
            return $chatId !== '' ? $chatId : __('Not configured', 'telegram-manager-woocommerce');
        }
        return substr($chatId, 0, 3) . '***' . substr($chatId, -3);
    }

    private function eventLabel(string $event): string
    {
        $event = sanitize_key($event);
        return match ($event) {
            'new_order', 'order_created' => __('New Order', 'telegram-manager-woocommerce'),
            'status_changed', 'order_status_changed' => __('Status Changed', 'telegram-manager-woocommerce'),
            'refund', 'refund_created' => __('Refund', 'telegram-manager-woocommerce'),
            default => ucwords(str_replace('_', ' ', $event)),
        };
    }

    private function guard(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }
    }
}
