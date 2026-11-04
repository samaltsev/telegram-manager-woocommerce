<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderActionService
{
    public function changeStatus(int $orderId, string $status, string $note = ''): bool
    {
        if ($orderId <= 0 || !function_exists('wc_get_order')) {
            return false;
        }

        $allowed = ['processing', 'completed', 'cancelled', 'on-hold', 'pending'];
        $status = sanitize_key($status);
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $order = wc_get_order($orderId);
        if (!$order || !is_object($order) || !method_exists($order, 'update_status')) {
            return false;
        }

        $order->update_status($status, $note !== '' ? $note : 'Status changed from Telegram Control Center.');
        return true;
    }
}
