<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminBotMessageFactory
{
    public static function connected(): string
    {
        return "Telegram bot connected\n\nI will notify you about new orders, payments and customer messages.";
    }

    public static function help(): string
    {
        return "Help\n\n" .
            "/today - today's summary\n" .
            "/orders - latest orders\n" .
            "/support - customer messages\n" .
            "/settings - notification settings\n" .
            "/stop - pause admin alerts";
    }

    /**
     * @param array<int, WC_Order> $orders
     */
    public static function orders(array $orders): string
    {
        if ($orders === []) {
            return "Latest orders\n\nNo recent orders.";
        }

        $lines = ["Latest orders", ''];
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order) {
                continue;
            }
            $status = function_exists('wc_get_order_status_name') ? (string) wc_get_order_status_name((string) $order->get_status()) : (string) $order->get_status();
            $total = trim((string) strip_tags((string) $order->get_formatted_order_total()));
            $lines[] = '#' . $order->get_id() . ' - ' . $status . ' - ' . $total;
        }

        return implode("\n", $lines);
    }

    public static function unknownCommand(): string
    {
        return 'Use /help to see available commands.';
    }
}