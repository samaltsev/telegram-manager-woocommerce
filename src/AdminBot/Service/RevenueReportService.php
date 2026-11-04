<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class RevenueReportService
{
    /** @return array<string, mixed> */
    public function report(string $period = 'today'): array
    {
        $orders = $this->ordersForPeriod($period);
        $revenue = 0.0;
        $paid = 0;
        $cancelled = 0;
        $paidStatuses = function_exists('wc_get_is_paid_statuses') ? (array) wc_get_is_paid_statuses() : ['processing', 'completed'];

        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_total')) {
                continue;
            }
            $status = (string) $order->get_status();
            if (in_array($status, $paidStatuses, true)) {
                $paid++;
                $revenue += (float) $order->get_total();
            }
            if ($status === 'cancelled') {
                $cancelled++;
            }
        }

        $count = count($orders);
        return [
            'period' => $period,
            'orders' => $count,
            'revenue' => $revenue,
            'average_order_value' => $paid > 0 ? $revenue / $paid : 0.0,
            'paid_orders' => $paid,
            'cancelled_orders' => $cancelled,
        ];
    }

    /** @return array<int, mixed> */
    private function ordersForPeriod(string $period): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        $now = current_time('timestamp');
        $period = sanitize_key($period);
        if ($period === 'week') {
            $start = strtotime('-6 days 00:00:00', $now);
        } elseif ($period === 'month') {
            $start = strtotime(date('Y-m-01 00:00:00', $now));
        } else {
            $start = strtotime(date('Y-m-d 00:00:00', $now));
        }
        $end = strtotime(date('Y-m-d 23:59:59', $now));

        return (array) wc_get_orders([
            'limit' => -1,
            'date_created' => wp_date('Y-m-d H:i:s', $start) . '...' . wp_date('Y-m-d H:i:s', $end),
        ]);
    }
}
