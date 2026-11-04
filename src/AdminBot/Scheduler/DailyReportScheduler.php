<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Scheduler;

if (!defined('ABSPATH')) {
    exit;
}

final class DailyReportScheduler
{
    public const HOOK = 'wctm_admin_daily_report';

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
        }
    }
}
