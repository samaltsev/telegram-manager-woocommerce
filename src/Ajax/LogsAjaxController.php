<?php
declare(strict_types=1);

namespace WCTM\Ajax;

use WCTM\Database\LoggerRepository;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

class LogsAjaxController extends AbstractAjaxController
{
    private LoggerRepository $logs;
    private DbQueueRepository $queueRepo;

    public function __construct(
        BotSettingsRepository $botSettings,
        LoggerRepository $logs,
        DbQueueRepository $queueRepo
    ) {
        parent::__construct($botSettings);
        $this->logs = $logs;
        $this->queueRepo = $queueRepo;
    }

    public function clear_logs()
    {
        $this->guard();
        $this->logs->clear();
        wp_send_json_success(['message' => 'Logs cleared']);
    }

    public function clear_success_logs()
    {
        $this->guard();
        $cleared = $this->logs->clearByLevel('info');
        wp_send_json_success([
            'message' => sprintf('Cleared %d successful log(s).', $cleared),
            'cleared' => $cleared,
        ]);
    }

    public function save_log_settings()
    {
        $this->guard();

        $retentionDays = isset($_POST['retention_days']) ? absint((string) wp_unslash($_POST['retention_days'])) : 7;
        if (!in_array($retentionDays, [3, 7, 14, 30], true)) {
            $retentionDays = 7;
        }

        $undeliveredLimit = isset($_POST['undelivered_limit']) ? absint((string) wp_unslash($_POST['undelivered_limit'])) : 10;
        if (!in_array($undeliveredLimit, [5, 10, 15], true)) {
            $undeliveredLimit = 10;
        }

        $storeSuccessLogs = isset($_POST['store_success_logs']) && wp_unslash((string) $_POST['store_success_logs']) === '1';
        $hideSuccessLogs = isset($_POST['hide_success_logs']) && wp_unslash((string) $_POST['hide_success_logs']) === '1';

        update_option('wctm_log_retention_days', $retentionDays, false);
        update_option('wctm_undelivered_records_limit', $undeliveredLimit, false);
        update_option('wctm_store_success_logs', $storeSuccessLogs, false);
        update_option('wctm_hide_success_logs', $hideSuccessLogs, false);

        wp_send_json_success([
            'message' => 'Log settings saved.',
            'retention_days' => $retentionDays,
            'undelivered_limit' => $undeliveredLimit,
            'store_success_logs' => $storeSuccessLogs,
            'hide_success_logs' => $hideSuccessLogs,
        ]);
    }

    public function clear_stuck_jobs()
    {
        $this->guard();
        $released = $this->queueRepo->clearStuckJobs(10);

        wp_send_json_success([
            'message' => sprintf('Released %d stuck job(s).', $released),
            'released' => $released,
        ]);
    }

    public function retry_failed_jobs()
    {
        $this->guard();
        $retried = $this->queueRepo->retryFailedJobs();

        wp_send_json_success([
            'message' => sprintf('Retried %d failed job(s).', $retried),
            'retried' => $retried,
        ]);
    }

    public function clear_failed_jobs()
    {
        $this->guard();
        $cleared = $this->queueRepo->clearFailedJobs();

        wp_send_json_success([
            'message' => sprintf('Removed %d failed job(s).', $cleared),
            'cleared' => $cleared,
        ]);
    }

    public function retry_failed_job()
    {
        $this->guard();
        $jobId = isset($_POST['job_id']) ? absint((string) wp_unslash($_POST['job_id'])) : 0;
        if ($jobId <= 0) {
            wp_send_json_error(['message' => 'Invalid job id.'], 400);
        }

        $updated = $this->queueRepo->retryFailedJob($jobId);
        if ($updated <= 0) {
            wp_send_json_error(['message' => 'Failed job not found or already processed.'], 404);
        }

        wp_send_json_success([
            'message' => 'Failed job scheduled for retry.',
            'job_id' => $jobId,
        ]);
    }

    public function failed_job_details(): void
    {
        $this->guard();
        $jobId = isset($_POST['job_id']) ? absint((string) wp_unslash($_POST['job_id'])) : 0;
        if ($jobId <= 0) {
            wp_send_json_error(['message' => 'Invalid job id.'], 400);
        }

        $job = $this->queueRepo->failedJobDetails($jobId);
        if ($job === null) {
            wp_send_json_error(['message' => 'Failed job not found.'], 404);
        }

        wp_send_json_success([
            'job' => $job,
            'attempts' => $this->queueRepo->attemptHistory($jobId),
        ]);
    }

    public function cleanup_old_records()
    {
        $this->guard();

        $retentionDays = (int) get_option('wctm_log_retention_days', 7);
        $deletedLogs = $this->logs->cleanupOld($retentionDays);
        $deletedJobs = $this->queueRepo->cleanupOldJobs(14, 30);

        wp_send_json_success([
            'message' => sprintf('Cleanup completed. Deleted %d log(s) and %d old queue job(s).', $deletedLogs, $deletedJobs),
            'deleted_logs' => $deletedLogs,
            'deleted_jobs' => $deletedJobs,
        ]);
    }

    public function clear_undelivered_jobs()
    {
        $this->guard();
        $cleared = $this->queueRepo->clearUndeliveredJobs();

        wp_send_json_success([
            'message' => sprintf('Deleted %d undelivered record(s).', $cleared),
            'cleared' => $cleared,
        ]);
    }
}
