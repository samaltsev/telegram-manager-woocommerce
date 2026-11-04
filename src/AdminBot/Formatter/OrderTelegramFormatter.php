<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Formatter;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderTelegramFormatter
{
    public function listTitle(array $orders, string $title = '📦 Latest orders'): string
    {
        if ($orders === []) {
            return $title . "\n\nNo orders found.";
        }

        $lines = [$title, ''];
        foreach ($orders as $order) {
            if (!is_object($order) || !method_exists($order, 'get_id')) {
                continue;
            }
            $id = (int) $order->get_id();
            $statusKey = (string) $order->get_status();
            $status = function_exists('wc_get_order_status_name') ? (string) wc_get_order_status_name($statusKey) : $statusKey;
            $total = method_exists($order, 'get_formatted_order_total') ? html_entity_decode(wp_strip_all_tags((string) $order->get_formatted_order_total()), ENT_QUOTES, 'UTF-8') : '';
            $lines[] = "#{$id} — {$status} — {$total}";
        }

        return trim(implode("\n", $lines));
    }
}
