<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

$queueStats = (array) ($queueStats ?? []);
$logs = (array) ($logs ?? []);
$failedJobs = (array) ($failedJobs ?? []);
$logsTotal = isset($logsTotal) ? (int) $logsTotal : count($logs);
$failedCount = (int) ($queueStats['failed'] ?? count($failedJobs));
$queuedCount = (int) (($queueStats['pending'] ?? 0) + ($queueStats['processing'] ?? 0) + ($queueStats['retry'] ?? 0));
$lastError = '—';
foreach ($logs as $row) {
    if (($row['level'] ?? '') === 'error') {
        $lastError = (string) ($row['message'] ?? '—');
        break;
    }
}

$title = __('Logs', 'telegram-manager-woocommerce');
$subtitle = __('Telegram delivery and queue diagnostics.', 'telegram-manager-woocommerce');
$actions = [
    ['type' => 'text', 'label' => 'v' . (defined('WCTM_VERSION') ? WCTM_VERSION : '')],
];

include WCTM_PATH . 'views/layout-start.php';
include WCTM_PATH . 'views/header.php';
?>

<div class="tcc-page-content tcc-logs-page">
    <div class="tcc-actions-row tcc-mb-3">
        <button type="button" class="tcc-btn tcc-btn-primary" data-wctm-log-action="retry_failed_jobs"><?php esc_html_e('Retry failed', 'telegram-manager-woocommerce'); ?></button>
        <button type="button" class="tcc-btn tcc-btn-secondary" data-wctm-log-action="cleanup_old_records"><?php esc_html_e('Cleanup old', 'telegram-manager-woocommerce'); ?></button>
        <button type="button" class="tcc-btn tcc-btn-secondary" data-wctm-log-action="clear_success_logs"><?php esc_html_e('Clear successful', 'telegram-manager-woocommerce'); ?></button>
        <button type="button" class="tcc-btn tcc-btn-danger" data-wctm-log-action="clear_logs"><?php esc_html_e('Clear all', 'telegram-manager-woocommerce'); ?></button>
    </div>

    <section class="tcc-card-grid tcc-mb-3">
        <article class="tcc-card tcc-stat-card"><div class="tcc-stat-label"><?php esc_html_e('Total logs', 'telegram-manager-woocommerce'); ?></div><div class="tcc-stat-value"><?php echo esc_html((string) $logsTotal); ?></div></article>
        <article class="tcc-card tcc-stat-card"><div class="tcc-stat-label"><?php esc_html_e('Queued', 'telegram-manager-woocommerce'); ?></div><div class="tcc-stat-value"><?php echo esc_html((string) $queuedCount); ?></div></article>
        <article class="tcc-card tcc-stat-card <?php echo esc_attr($failedCount > 0 ? 'is-error' : 'is-success'); ?>"><div class="tcc-stat-label"><?php esc_html_e('Failed', 'telegram-manager-woocommerce'); ?></div><div class="tcc-stat-value"><?php echo esc_html((string) $failedCount); ?></div></article>
        <article class="tcc-card tcc-stat-card"><div class="tcc-stat-label"><?php esc_html_e('Last error', 'telegram-manager-woocommerce'); ?></div><div class="tcc-stat-value tcc-value-compact"><?php echo esc_html(wp_trim_words($lastError, 5, '…')); ?></div></article>
    </section>

    <form method="get" class="tcc-card tcc-filter-bar tcc-mb-3">
        <input type="hidden" name="page" value="wctm-logs">
        <label><span><?php esc_html_e('Status', 'telegram-manager-woocommerce'); ?></span><select name="level"><option value=""><?php esc_html_e('All Status', 'telegram-manager-woocommerce'); ?></option><option value="info" <?php selected($selectedLevel ?? '', 'info'); ?>><?php esc_html_e('Success / Info', 'telegram-manager-woocommerce'); ?></option><option value="error" <?php selected($selectedLevel ?? '', 'error'); ?>><?php esc_html_e('Failed', 'telegram-manager-woocommerce'); ?></option><option value="warning" <?php selected($selectedLevel ?? '', 'warning'); ?>><?php esc_html_e('Warning', 'telegram-manager-woocommerce'); ?></option></select></label>
        <label><span><?php esc_html_e('Order ID', 'telegram-manager-woocommerce'); ?></span><input type="number" min="1" name="order_id" value="<?php echo esc_attr(isset($_GET['order_id']) ? (string) absint($_GET['order_id']) : ''); ?>" placeholder="4312"></label>
        <label><span><?php esc_html_e('Event', 'telegram-manager-woocommerce'); ?></span><select name="context"><option value=""><?php esc_html_e('All events', 'telegram-manager-woocommerce'); ?></option><option value="telegram_send" <?php selected($filters['context'] ?? '', 'telegram_send'); ?>><?php esc_html_e('Telegram send', 'telegram-manager-woocommerce'); ?></option><option value="queue" <?php selected($filters['context'] ?? '', 'queue'); ?>><?php esc_html_e('Queue', 'telegram-manager-woocommerce'); ?></option></select></label>
        <label class="tcc-filter-check"><input type="hidden" name="show_success" value="0"><input type="checkbox" name="show_success" value="1" <?php checked(!empty($showSuccess)); ?>><span><?php esc_html_e('Show successful logs', 'telegram-manager-woocommerce'); ?></span></label>
        <button type="submit" class="tcc-btn tcc-btn-primary"><?php esc_html_e('Apply filters', 'telegram-manager-woocommerce'); ?></button>
    </form>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head"><div><h2><?php esc_html_e('Failed Queue Jobs', 'telegram-manager-woocommerce'); ?></h2><p><?php esc_html_e('Jobs that could not be delivered to Telegram.', 'telegram-manager-woocommerce'); ?></p></div><?php if (empty($failedJobs)) : ?><span class="tcc-health-pill is-success"><?php esc_html_e('Everything is working', 'telegram-manager-woocommerce'); ?></span><?php endif; ?></div>
        <div class="tcc-table-wrap"><table class="tcc-table"><thead><tr><th><?php esc_html_e('ID', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Event', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Order', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Chat', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Attempts', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Error', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Updated', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Action', 'telegram-manager-woocommerce'); ?></th></tr></thead><tbody>
        <?php if (empty($failedJobs)) : ?>
            <tr><td colspan="8"><div class="tcc-empty-state"><span>✅</span><strong><?php esc_html_e('No failed jobs.', 'telegram-manager-woocommerce'); ?></strong><small><?php esc_html_e('Everything is working perfectly.', 'telegram-manager-woocommerce'); ?></small></div></td></tr>
        <?php else : foreach ($failedJobs as $job) : ?>
            <tr><td><?php echo esc_html((string) ($job['id'] ?? '')); ?></td><td><?php echo esc_html((string) ($job['event'] ?? '')); ?></td><td><?php echo esc_html(!empty($job['order_id']) ? '#' . (string) $job['order_id'] : '—'); ?></td><td><?php echo esc_html((string) ($job['chat_id'] ?? '')); ?></td><td><?php echo esc_html((string) (($job['attempts'] ?? '0') . '/' . ($job['max_attempts'] ?? '0'))); ?></td><td><?php echo esc_html((string) ($job['last_error_message'] ?? $job['last_error_code'] ?? '')); ?></td><td><?php echo esc_html((string) ($job['updated_at'] ?? '')); ?></td><td><button type="button" class="tcc-btn tcc-btn-secondary tcc-btn-sm" data-wctm-retry-job="<?php echo esc_attr((string) ($job['id'] ?? '0')); ?>"><?php esc_html_e('Retry', 'telegram-manager-woocommerce'); ?></button></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>

    <section class="tcc-card">
        <div class="tcc-section-head"><div><h2><?php esc_html_e('Delivery Logs', 'telegram-manager-woocommerce'); ?></h2><p><?php esc_html_e('Latest Telegram delivery records.', 'telegram-manager-woocommerce'); ?></p></div></div>
        <div class="tcc-table-wrap"><table class="tcc-table"><thead><tr><th><?php esc_html_e('Date', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Level', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Event', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Order', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Chat', 'telegram-manager-woocommerce'); ?></th><th><?php esc_html_e('Message', 'telegram-manager-woocommerce'); ?></th></tr></thead><tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="6"><div class="tcc-empty-state"><span>🧾</span><strong><?php esc_html_e('No logs found.', 'telegram-manager-woocommerce'); ?></strong><small><?php esc_html_e('Delivery logs will appear here after notifications are processed.', 'telegram-manager-woocommerce'); ?></small></div></td></tr>
        <?php else : foreach ($logs as $row) :
            $level = sanitize_key((string) ($row['level'] ?? 'info'));
            $badgeClass = $level === 'error' ? 'is-error' : ($level === 'warning' ? 'is-warning' : 'is-success');
            $badgeText = $level === 'error' ? __('Failed', 'telegram-manager-woocommerce') : ($level === 'warning' ? __('Warning', 'telegram-manager-woocommerce') : __('Delivered', 'telegram-manager-woocommerce'));
        ?>
            <tr><td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td><td><span class="tcc-mini-badge <?php echo esc_attr($badgeClass); ?>"><?php echo esc_html($badgeText); ?></span></td><td><?php echo esc_html((string) ($row['event'] ?? $row['context'] ?? '')); ?></td><td><?php echo esc_html(!empty($row['order_id']) ? '#' . (string) $row['order_id'] : '—'); ?></td><td><?php echo esc_html((string) ($row['chat_id'] ?? '')); ?></td><td><?php echo esc_html((string) ($row['message'] ?? '')); ?></td></tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </section>
</div>

<?php include WCTM_PATH . 'views/layout-end.php'; ?>
