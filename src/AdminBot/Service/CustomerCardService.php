<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerCardService
{
    /** @return array<string, mixed>|null */
    public function fromOrderId(int $orderId): ?array
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return null;
        }
        $order = wc_get_order($orderId);
        if (!$order || !is_object($order)) {
            return null;
        }

        $customerId = method_exists($order, 'get_customer_id') ? (int) $order->get_customer_id() : 0;
        return [
            'customer_id' => $customerId,
            'name' => trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())),
            'phone' => (string) $order->get_billing_phone(),
            'email' => (string) $order->get_billing_email(),
            'orders_count' => $customerId > 0 && function_exists('wc_get_customer_order_count') ? (int) wc_get_customer_order_count($customerId) : 1,
            'total_spent' => $customerId > 0 && function_exists('wc_get_customer_total_spent') ? (float) wc_get_customer_total_spent($customerId) : (float) $order->get_total(),
            'last_order_id' => $orderId,
            'telegram_id' => '',
            'tags' => '',
            'notes' => '',
        ];
    }
}
