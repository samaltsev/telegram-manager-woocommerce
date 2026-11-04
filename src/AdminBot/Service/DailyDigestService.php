<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class DailyDigestService
{
    public function __construct(private RevenueReportService $revenueReportService) {}

    public function build(): array
    {
        return $this->revenueReportService->report('today');
    }
}
