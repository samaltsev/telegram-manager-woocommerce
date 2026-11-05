<?php
declare(strict_types=1);

namespace WCTM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class OrdersPageController
{
    public function render(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }

        $orders = function_exists('wc_get_orders')
            ? wc_get_orders([
                'limit'   => 50,
                'orderby' => 'date',
                'order'   => 'DESC',
                'type'    => 'shop_order',
                'return'  => 'objects',
            ])
            : [];

        echo '<div class="wrap wctm-admin"><h1>Orders</h1><p>Recent WooCommerce orders available to the Telegram admin workflow.</p>';
        echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr></thead><tbody>';

        $hasRows = false;

        foreach ((array) $orders as $order) {
            if (!$this->isDisplayableOrder($order)) {
                continue;
            }

            $hasRows = true;

            $id = (int) $order->get_id();
            $url = method_exists($order, 'get_edit_order_url')
                ? (string) $order->get_edit_order_url()
                : admin_url('post.php?post=' . $id . '&action=edit');

            $name = $this->getCustomerName($order);
            $date = method_exists($order, 'get_date_created') ? $order->get_date_created() : null;
            $status = method_exists($order, 'get_status') ? (string) $order->get_status() : '';

            echo '<tr><td><a href="' . esc_url($url) . '">#' . esc_html((string) $id) . '</a></td>';
            echo '<td>' . esc_html($name !== '' ? $name : __('Guest', 'telegram-manager-woocommerce')) . '</td>';
            echo '<td>' . esc_html(function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : $status) . '</td>';
            echo '<td>' . wp_kses_post(method_exists($order, 'get_formatted_order_total') ? (string) $order->get_formatted_order_total() : '') . '</td>';
            echo '<td>' . esc_html($date && method_exists($date, 'date_i18n') ? $date->date_i18n(get_option('date_format') . ' ' . get_option('time_format')) : '') . '</td></tr>';
        }

        if (!$hasRows) {
            echo '<tr><td colspan="5">' . esc_html__('No orders found.', 'telegram-manager-woocommerce') . '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    private function isDisplayableOrder($order): bool
    {
        if (!is_object($order) || !method_exists($order, 'get_id')) {
            return false;
        }

        if (class_exists('\WC_Order_Refund') && $order instanceof \WC_Order_Refund) {
            return false;
        }

        if (class_exists('\Automattic\WooCommerce\Admin\Overrides\OrderRefund') && $order instanceof \Automattic\WooCommerce\Admin\Overrides\OrderRefund) {
            return false;
        }

        if (method_exists($order, 'get_type') && (string) $order->get_type() !== 'shop_order') {
            return false;
        }

        return method_exists($order, 'get_formatted_order_total');
    }

    private function getCustomerName($order): string
    {
        $first = method_exists($order, 'get_billing_first_name') ? (string) $order->get_billing_first_name() : '';
        $last = method_exists($order, 'get_billing_last_name') ? (string) $order->get_billing_last_name() : '';

        return trim($first . ' ' . $last);
    }
}
