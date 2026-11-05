<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderSummaryService
{
    /** @return array<int, mixed> */
    public function latest(int $limit = 5): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        return (array) wc_get_orders([
            'limit' => max(1, min(10, $limit)),
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }

    /** @return array<int, mixed> */
    public function today(): array
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }
        $start = wp_date('Y-m-d 00:00:00', current_time('timestamp'));
        $end = wp_date('Y-m-d 23:59:59', current_time('timestamp'));
        return (array) wc_get_orders([
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'date_created' => $start . '...' . $end,
        ]);
    }
}
