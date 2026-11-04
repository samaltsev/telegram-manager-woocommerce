<?php
declare(strict_types=1);
namespace WCTM\Queue;

use WCTM\Infrastructure\Queue\QueueWorker;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;
use WCTM\Database\LoggerRepository;

if (!defined('ABSPATH')) {
    exit;
}

class QueueScheduler
{
    const CRON_HOOK = 'wctm_process_queue';
    const CLEANUP_LOGS_HOOK = 'wctm_cleanup_logs';

    protected QueueWorker $worker;

    public function __construct(
        QueueWorker $worker,
        private DbQueueRepository $queueRepository,
        private LoggerRepository $loggerRepository
    )
    {
        $this->worker = $worker;
    }

    public function registerCronHooks()
    {
        add_filter('cron_schedules', array($this, 'cronSchedules'));
        add_action(self::CRON_HOOK, array($this, 'runWorker'));
        add_action('wctm_process_queue_single', array($this, 'runWorker'));
        add_action(self::CLEANUP_LOGS_HOOK, array($this, 'cleanupLogs'));
    }

    public function cronSchedules($schedules)
    {
        return self::addCronSchedules((array) $schedules);
    }

    public static function scheduleRecurring()
    {
        // Activation may run after `init`, before the provider's filter is active.
        add_filter('cron_schedules', [self::class, 'addCronSchedules']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'wctm_every_five_minutes', self::CRON_HOOK);
        }

        if (!wp_next_scheduled(self::CLEANUP_LOGS_HOOK)) {
            wp_schedule_event(time() + 120, 'daily', self::CLEANUP_LOGS_HOOK);
        }

        remove_filter('cron_schedules', [self::class, 'addCronSchedules']);
    }

    public static function addCronSchedules(array $schedules): array
    {
        $schedules['wctm_every_five_minutes'] = [
            'interval' => 300,
            'display' => 'Every 5 minutes',
        ];

        return $schedules;
    }

    public static function unscheduleRecurring()
    {
        while ($timestamp = wp_next_scheduled(self::CRON_HOOK)) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
        while ($timestamp = wp_next_scheduled('wctm_process_queue_single')) {
            wp_unschedule_event($timestamp, 'wctm_process_queue_single');
        }
        while ($timestamp = wp_next_scheduled(self::CLEANUP_LOGS_HOOK)) {
            wp_unschedule_event($timestamp, self::CLEANUP_LOGS_HOOK);
        }
    }

    public static function scheduleSingle()
    {
        if (!wp_next_scheduled('wctm_process_queue_single')) {
            wp_schedule_single_event(time() + 1, 'wctm_process_queue_single');
        }
    }

    public function runWorker()
    {
        $this->worker->runOnce(10);
    }

    public function cleanupLogs(): void
    {
        $retentionDays = (int) get_option('wctm_log_retention_days', 7);
        if (!in_array($retentionDays, [3, 7, 14, 30], true)) {
            $retentionDays = 7;
        }

        $deletedLogs = $this->loggerRepository->cleanupOld($retentionDays);
        $deletedJobs = $this->queueRepository->cleanupOldJobs(14, 30);

        update_option('wctm_last_cleanup_result', wp_json_encode([
            'deleted_logs' => $deletedLogs,
            'deleted_jobs' => $deletedJobs,
            'run_at' => current_time('mysql'),
        ]), false);
    }
}
