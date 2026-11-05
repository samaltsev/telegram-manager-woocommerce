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
        $orderId = (int) $orderId;
        if ($orderId <= 0) {
            return;
        }

        $settings = (array) get_option('wctm_settings', []);
        // WooCommerce can reduce stock after the new-order hook. When remaining
        // quantities are requested, build the message at the end of the request
        // instead, keeping the normal fast queue and delayed empty-item retry.
        if (!empty($settings['show_new_order_stock'])
            && doing_action('woocommerce_new_order')
            && !did_action('shutdown')) {
            add_action('shutdown', function () use ($orderId): void {
                $freshOrder = wc_get_order($orderId);
                if ($freshOrder instanceof \WC_Order) {
                    $this->notifier->notifyNewOrder($freshOrder, true);
                }
            }, 999);
            return;
        }
        $order = wc_get_order($orderId);
        if ($order instanceof \WC_Order) {
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
