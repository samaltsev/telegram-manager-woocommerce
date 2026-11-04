<?php
declare(strict_types=1);

namespace WCTM\Notifications;

use WCTM\Notifications\UnifiedNotifier;

class OrderNotifierService
{
    protected UnifiedNotifier $notifier;

    public function __construct(UnifiedNotifier $notifier)
    {
        $this->notifier = $notifier;
    }

    public function notifyNewOrder($orderId)
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $this->notifier->notifyNewOrder($order, true);
        }
    }

    public function notifyOrderStatusChange($orderId, $oldStatus, $newStatus)
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $this->notifier->notifyOrderStatusChange($order, $oldStatus, $newStatus, true);
        }
    }

    public function notifyPaymentReceived($orderId)
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $this->notifier->notifyPaymentReceived($order, true);
        }
    }

    public function notifyRefundCreated($orderId, $refundId = 0)
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $this->notifier->notifyRefundCreated($order, (int) $refundId, true);
        }
    }
}
