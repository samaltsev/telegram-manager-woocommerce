<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Formatter;

if (!defined('ABSPATH')) {
    exit;
}

final class RevenueReportFormatter
{
    public function format(array $report): string
    {
        $money = static function (float $amount): string {
            return function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags((string) wc_price($amount)), ENT_QUOTES, 'UTF-8') : number_format($amount, 2, '.', ' ');
        };

        return "💰 Revenue report ({$report['period']})\n\n"
            . 'Revenue: ' . $money((float) $report['revenue']) . "\n"
            . 'Orders: ' . (int) $report['orders'] . "\n"
            . 'Average order value: ' . $money((float) $report['average_order_value']) . "\n"
            . 'Paid orders: ' . (int) $report['paid_orders'] . "\n"
            . 'Cancelled orders: ' . (int) $report['cancelled_orders'];
    }
}
