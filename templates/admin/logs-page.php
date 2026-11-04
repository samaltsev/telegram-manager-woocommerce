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
?>
<div class="wrap wctm-admin wctm-modern-page wctm-logs-page">
    <div class="wctm-dash-header">
        <div>
            <h1><?php esc_html_e('Logs', 'telegram-manager-woocommerce'); ?></h1>
            <p><?php esc_html_e('Telegram delivery and queue diagnostics.', 'telegram-manager-woocommerce'); ?></p>
        </div>
        <div class="wctm-dash-actions">
            <button type="button" class="wctm-btn wctm-btn-primary" data-wctm-log-action="retry_failed_jobs"><?php esc_html_e('Retry failed', 'telegram-manager-woocommerce'); ?></button>
            <button type="button" class="wctm-btn wctm-btn-secondary" data-wctm-log-action="cleanup_old_records"><?php esc_html_e('Cleanup old', 'telegram-manager-woocommerce'); ?></button>
            <button type="button" class="wctm-btn wctm-btn-secondary" data-wctm-log-action="clear_success_logs"><?php esc_html_e('Clear successful', 'telegram-manager-woocommerce'); ?></button>
            <button type="button" class="wctm-btn wctm-btn-danger" data-wctm-log-action="clear_logs"><?php esc_html_e('Clear all', 'telegram-manager-woocommerce'); ?></button>
        </div>
    </div>

    <section class="wctm-stat-grid wctm-log-stats">
        <article class="wctm-modern-stat">
            <div><span><?php esc_html_e('Total logs', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) $logsTotal); ?></strong></div><em>📚</em>
        </article>
        <article class="wctm-modern-stat">
            <div><span><?php esc_html_e('Queued', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) $queuedCount); ?></strong></div><em>⏳</em>
        </article>
        <article class="wctm-modern-stat <?php echo esc_attr($failedCount > 0 ? 'is-error' : 'is-ok'); ?>">
            <div><span><?php esc_html_e('Failed', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) $failedCount); ?></strong></div><em>❌</em>
        </article>
        <article class="wctm-modern-stat">
            <div><span><?php esc_html_e('Last error', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html(wp_trim_words($lastError, 5, '…')); ?></strong></div><em>🧾</em>
        </article>
    </section>

    <form method="get" class="wctm-modern-card wctm-log-filters">
        <input type="hidden" name="page" value="wctm-logs">
        <label>
            <span><?php esc_html_e('Status', 'telegram-manager-woocommerce'); ?></span>
            <select name="level">
                <option value=""><?php esc_html_e('All Status', 'telegram-manager-woocommerce'); ?></option>
                <option value="info" <?php selected($selectedLevel ?? '', 'info'); ?>><?php esc_html_e('Success / Info', 'telegram-manager-woocommerce'); ?></option>
                <option value="error" <?php selected($selectedLevel ?? '', 'error'); ?>><?php esc_html_e('Failed', 'telegram-manager-woocommerce'); ?></option>
                <option value="warning" <?php selected($selectedLevel ?? '', 'warning'); ?>><?php esc_html_e('Warning', 'telegram-manager-woocommerce'); ?></option>
            </select>
        </label>
        <label>
            <span><?php esc_html_e('Order ID', 'telegram-manager-woocommerce'); ?></span>
            <input type="number" min="1" name="order_id" value="<?php echo esc_attr(isset($_GET['order_id']) ? (string) absint($_GET['order_id']) : ''); ?>" placeholder="4312">
        </label>
        <label>
            <span><?php esc_html_e('Context', 'telegram-manager-woocommerce'); ?></span>
            <select name="context">
                <option value=""><?php esc_html_e('All events', 'telegram-manager-woocommerce'); ?></option>
                <option value="telegram_send" <?php selected($filters['context'] ?? '', 'telegram_send'); ?>><?php esc_html_e('Telegram send', 'telegram-manager-woocommerce'); ?></option>
                <option value="queue" <?php selected($filters['context'] ?? '', 'queue'); ?>><?php esc_html_e('Queue', 'telegram-manager-woocommerce'); ?></option>
            </select>
        </label>
        <label class="wctm-log-filter-check">
            <input type="hidden" name="show_success" value="0">
            <input type="checkbox" name="show_success" value="1" <?php checked(!empty($showSuccess)); ?>>
            <span><?php esc_html_e('Show successful logs', 'telegram-manager-woocommerce'); ?></span>
        </label>
        <button type="submit" class="wctm-btn wctm-btn-primary"><?php esc_html_e('Apply filters', 'telegram-manager-woocommerce'); ?></button>
    </form>

    <section class="wctm-modern-card">
        <div class="wctm-card-head">
            <div>
                <h2><?php esc_html_e('Failed Queue Jobs', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Jobs that could not be delivered to Telegram.', 'telegram-manager-woocommerce'); ?></p>
            </div>
            <?php if (empty($failedJobs)) : ?>
                <span class="wctm-health-pill is-ok"><?php esc_html_e('Everything is working', 'telegram-manager-woocommerce'); ?></span>
            <?php endif; ?>
        </div>

        <div class="wctm-modern-table-wrap">
            <table class="wctm-modern-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Event', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Order', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Chat', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Attempts', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Error', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Updated', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Action', 'telegram-manager-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($failedJobs)) : ?>
                        <tr><td colspan="8"><div class="wctm-empty-state"><span>✅</span><strong><?php esc_html_e('No failed jobs.', 'telegram-manager-woocommerce'); ?></strong><small><?php esc_html_e('Everything is working perfectly.', 'telegram-manager-woocommerce'); ?></small></div></td></tr>
                    <?php else : ?>
                        <?php foreach ($failedJobs as $job) : ?>
                            <tr>
                                <td><?php echo esc_html((string) ($job['id'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($job['event'] ?? '')); ?></td>
                                <td><?php echo esc_html(!empty($job['order_id']) ? '#' . (string) $job['order_id'] : '—'); ?></td>
                                <td><?php echo esc_html((string) ($job['chat_id'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) (($job['attempts'] ?? '0') . '/' . ($job['max_attempts'] ?? '0'))); ?></td>
                                <td><?php echo esc_html((string) ($job['last_error_message'] ?? $job['last_error_code'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($job['updated_at'] ?? '')); ?></td>
                                <td><button type="button" class="wctm-btn wctm-btn-secondary wctm-btn-small" data-wctm-retry-job="<?php echo esc_attr((string) ($job['id'] ?? '0')); ?>"><?php esc_html_e('Retry', 'telegram-manager-woocommerce'); ?></button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="wctm-modern-card">
        <div class="wctm-card-head">
            <div>
                <h2><?php esc_html_e('Delivery Logs', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Latest Telegram delivery records.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </div>

        <div class="wctm-modern-table-wrap">
            <table class="wctm-modern-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Level', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Event', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Order', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Chat', 'telegram-manager-woocommerce'); ?></th>
                        <th><?php esc_html_e('Message', 'telegram-manager-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr><td colspan="6"><div class="wctm-empty-state"><span>🧾</span><strong><?php esc_html_e('No logs found.', 'telegram-manager-woocommerce'); ?></strong><small><?php esc_html_e('Delivery logs will appear here after notifications are processed.', 'telegram-manager-woocommerce'); ?></small></div></td></tr>
                    <?php else : ?>
                        <?php foreach ($logs as $row) :
                            $level = sanitize_key((string) ($row['level'] ?? 'info'));
                            $badgeClass = $level === 'error' ? 'is-error' : ($level === 'warning' ? 'is-warn' : 'is-ok');
                            $badgeText = $level === 'error' ? __('Failed', 'telegram-manager-woocommerce') : ($level === 'warning' ? __('Warning', 'telegram-manager-woocommerce') : __('Delivered', 'telegram-manager-woocommerce'));
                        ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['created_at'] ?? '')); ?></td>
                                <td><span class="wctm-mini-badge <?php echo esc_attr($badgeClass); ?>"><?php echo esc_html($badgeText); ?></span></td>
                                <td><?php echo esc_html((string) ($row['event'] ?? $row['context'] ?? '')); ?></td>
                                <td><?php echo esc_html(!empty($row['order_id']) ? '#' . (string) $row['order_id'] : '—'); ?></td>
                                <td><?php echo esc_html((string) ($row['chat_id'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['message'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
