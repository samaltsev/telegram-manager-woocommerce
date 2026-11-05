<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Database\LoggerRepository;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class LogsPageController
{
    public function __construct(
        private LoggerRepository $logs,
        private DbQueueRepository $queueRepo
    ) {
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }

        $hideSuccessDefault = (bool) get_option('wctm_hide_success_logs', true);
        $showSuccess = isset($_GET['show_success']) ? wp_unslash((string) $_GET['show_success']) === '1' : !$hideSuccessDefault;
        $selectedLevel = isset($_GET['level']) ? sanitize_key(wp_unslash($_GET['level'])) : '';
        $filters = [
            'context' => isset($_GET['context']) ? sanitize_key(wp_unslash($_GET['context'])) : '',
            'level' => $selectedLevel,
            'order_id' => isset($_GET['order_id']) ? absint($_GET['order_id']) : 0,
        ];
        if (!$showSuccess && $selectedLevel === '') {
            $filters['exclude_level'] = 'info';
        }

        ViewRenderer::render('logs', [
            'hideSuccessDefault' => $hideSuccessDefault,
            'showSuccess' => $showSuccess,
            'selectedLevel' => $selectedLevel,
            'filters' => $filters,
            'retentionDays' => (int) get_option('wctm_log_retention_days', 7),
            'undeliveredLimit' => (int) get_option('wctm_undelivered_records_limit', 10),
            'storeSuccessLogs' => (bool) get_option('wctm_store_success_logs', false),
            'logs' => $this->logs->query($filters, 200, 0),
            'stats' => $this->logs->stats(),
            'queueStats' => $this->queueRepo->stats(),
            'failedJobs' => $this->queueRepo->failedJobs(20),
            'undeliveredJobs' => $this->queueRepo->undeliveredJobs((int) get_option('wctm_undelivered_records_limit', 10)),
            'recentAttempts' => $this->queueRepo->recentAttempts(12),
            'logsTotal' => $this->logs->countAll(),
            'logsInfo' => $this->logs->countByLevel('info'),
        ]);
    }
}
