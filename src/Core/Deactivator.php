<?php
declare(strict_types=1);
namespace WCTM\Core;

use WCTM\Queue\QueueScheduler;
use WCTM\AdminBot\Scheduler\DailyReportScheduler;
use WCTM\AdminBot\Scheduler\LowStockScheduler;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator
{
    public static function deactivate()
    {
        QueueScheduler::unscheduleRecurring();
        DailyReportScheduler::unschedule();
        LowStockScheduler::unschedule();
        flush_rewrite_rules();
    }
}
