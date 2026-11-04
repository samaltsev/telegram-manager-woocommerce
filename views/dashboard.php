<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}

include WCTM_PATH . 'views/layout-start.php';
include WCTM_PATH . 'views/header.php';

$statusPill = static function (bool $ok): void {
    echo '<span class="tcc-status-dot ' . esc_attr($ok ? 'is-success' : 'is-warning') . '" aria-hidden="true"></span>';
};

$summaryItem = static function (string $label, string $value, bool $ok) use ($statusPill): void {
    echo '<div class="tcc-summary-item">';
    $statusPill($ok);
    echo '<span>' . esc_html($label) . '</span>';
    echo '<strong>' . esc_html($value) . '</strong>';
    echo '</div>';
};

$statCard = static function (string $label, string $value, string $icon, string $tone, bool $ok = true) use ($statusPill): void {
    echo '<article class="tcc-card tcc-stat-card tcc-card-hover">';
    echo '<div class="tcc-stat-label"><span class="dashicons ' . esc_attr($icon) . ' tcc-icon-' . esc_attr($tone) . '" aria-hidden="true"></span>' . esc_html($label) . '</div>';
    echo '<div class="tcc-stat-value">';
    if ($label !== __('Notifications Today', 'telegram-manager-woocommerce') && $label !== __('Failed', 'telegram-manager-woocommerce')) {
        $statusPill($ok);
    }
    echo esc_html($value) . '</div>';
    echo '</article>';
};

$metric = static function (string $label, string $value): void {
    echo '<div class="tcc-queue-metric"><strong>' . esc_html($value) . '</strong><span>' . esc_html($label) . '</span></div>';
};

$check = static function (string $label, bool $done): void {
    echo '<span class="tcc-check-item ' . esc_attr($done ? 'is-done' : 'is-open') . '"><span class="dashicons ' . esc_attr($done ? 'dashicons-yes' : 'dashicons-warning') . '" aria-hidden="true"></span>' . esc_html($label) . '</span>';
};
?>

<div class="tcc-page-content tcc-dashboard-page">
    <section class="tcc-status-summary">
        <?php
        $summaryItem(__('Bot', 'telegram-manager-woocommerce'), (string) $bot_status_label, (bool) $configured);
        $summaryItem(__('Chat', 'telegram-manager-woocommerce'), (string) $chat_label, (bool) $has_chat_id);
        $summaryItem(__('Queue', 'telegram-manager-woocommerce'), (string) $queue_status_label, (bool) $queue_healthy);
        $summaryItem(__('Last message', 'telegram-manager-woocommerce'), (string) $last_message_human, (bool) $has_last_message);
        ?>
    </section>

    <section class="tcc-card-grid">
        <?php
        $statCard(__('Telegram Bot', 'telegram-manager-woocommerce'), (string) $bot_status_label, 'dashicons-format-chat', 'telegram', (bool) $configured);
        $statCard(__('Queue', 'telegram-manager-woocommerce'), (string) $queue_status_label, 'dashicons-controls-forward', 'warning', (bool) $queue_healthy);
        $statCard(__('Notifications Today', 'telegram-manager-woocommerce'), (string) $notifications_today, 'dashicons-email', 'primary');
        $statCard(__('Last Notification', 'telegram-manager-woocommerce'), (string) $last_notification_label, 'dashicons-clock', 'muted', (bool) $has_last_message);
        $statCard(__('Failed', 'telegram-manager-woocommerce'), (string) $failed, 'dashicons-dismiss', 'error', ((int) $failed) === 0);
        ?>
    </section>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head">
            <div>
                <h2><?php esc_html_e('Delivery Queue', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Retry failed notifications or release stuck jobs.', 'telegram-manager-woocommerce'); ?></p>
            </div>
            <span class="tcc-health-pill <?php echo esc_attr((bool) $queue_healthy ? 'is-success' : 'is-warning'); ?>"><?php echo esc_html((string) $queue_status_label); ?></span>
        </div>

        <div class="tcc-queue-progress" aria-label="<?php esc_attr_e('Queue health', 'telegram-manager-woocommerce'); ?>">
            <span style="width: <?php echo esc_attr((string) ((int) $queue_progress)); ?>%"></span>
        </div>

        <div class="tcc-actions-row">
            <button type="button" class="tcc-btn tcc-btn-secondary tcc-btn-sm" data-wctm-ajax-action="clear_stuck_jobs">
                <span class="dashicons dashicons-controls-play" aria-hidden="true"></span> <?php esc_html_e('Release stuck jobs', 'telegram-manager-woocommerce'); ?>
            </button>
            <button type="button" class="tcc-btn tcc-btn-secondary tcc-btn-sm" data-wctm-ajax-action="retry_failed_jobs">
                <span class="dashicons dashicons-controls-repeat" aria-hidden="true"></span> <?php esc_html_e('Retry failed jobs', 'telegram-manager-woocommerce'); ?>
            </button>
        </div>
        <div id="wctm-dashboard-action-result" class="tcc-inline-result"></div>

        <div class="tcc-queue-metrics">
            <?php foreach ((array) $queue_metrics as $queueMetric) : ?>
                <?php $metric((string) ($queueMetric['label'] ?? ''), (string) ($queueMetric['value'] ?? '')); ?>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head">
            <div>
                <h2><?php esc_html_e('Setup checklist', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Everything needed for reliable Telegram notifications.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </div>
        <div class="tcc-checklist">
            <?php
            $check(__('Bot token configured', 'telegram-manager-woocommerce'), (bool) $has_token);
            $check(__('Telegram chat connected', 'telegram-manager-woocommerce'), (bool) $has_chat_id);
            $check(__('Test message delivered', 'telegram-manager-woocommerce'), (bool) $has_last_test);
            $check(__('Queue operational', 'telegram-manager-woocommerce'), (bool) $queue_healthy);
            ?>
        </div>
    </section>

    <section class="tcc-card">
        <div class="tcc-section-head">
            <div>
                <h2><?php esc_html_e('Recent Activity', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Latest delivery attempts.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </div>
        <?php if (empty($recent_activity)) : ?>
            <div class="tcc-empty-state">
                <span>📨</span>
                <strong><?php esc_html_e('No recent activity yet.', 'telegram-manager-woocommerce'); ?></strong>
                <small><?php esc_html_e('New WooCommerce notifications will appear here after delivery attempts.', 'telegram-manager-woocommerce'); ?></small>
            </div>
        <?php else : ?>
            <div class="tcc-activity-list">
                <?php foreach ((array) $recent_activity as $item) : ?>
                    <?php $isFailed = (($item['status'] ?? '') === 'failed'); ?>
                    <div class="tcc-activity-item">
                        <span class="tcc-status-dot <?php echo esc_attr($isFailed ? 'is-error' : 'is-success'); ?>" aria-hidden="true"></span>
                        <div>
                            <strong><?php echo esc_html((string) ($item['title'] ?? '')); ?></strong>
                            <small><?php echo esc_html((string) ($item['created_at'] ?? '')); ?></small>
                        </div>
                        <span class="tcc-mini-badge <?php echo esc_attr($isFailed ? 'is-error' : 'is-success'); ?>"><?php echo esc_html($isFailed ? __('Failed', 'telegram-manager-woocommerce') : __('Delivered', 'telegram-manager-woocommerce')); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php include WCTM_PATH . 'views/layout-end.php'; ?>
