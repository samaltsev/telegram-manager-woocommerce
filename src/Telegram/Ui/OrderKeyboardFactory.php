<?php
declare(strict_types=1);

namespace WCTM\Telegram\Ui;

use WC_Order;
use WCTM\Templates\TemplateTags;

if (!defined('ABSPATH')) {
    exit;
}

final class OrderKeyboardFactory
{
    /**
     * @return array<string, mixed>
     */
    public function forAdminOrder(WC_Order $order, bool $withActions = true): array
    {
        $tags = TemplateTags::forOrder($order);
        $orderId = (int) $order->get_id();
        $keyboard = [];

        $openUrl = html_entity_decode(trim((string) ($tags['admin_order_url'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $openUrl = str_replace(['&#038;', '&amp;'], '&', $openUrl);
        if ($this->isAllowedUrl($openUrl)) {
            $keyboard[] = [
                ['text' => __('🧾 Open Order', 'telegram-manager-woocommerce'), 'url' => $openUrl],
            ];
        }

        if ($withActions) {
            $keyboard[] = [
                ['text' => 'В обработку', 'callback_data' => "wctm_admin_order:{$orderId}:processing"],
                ['text' => 'Выполнен', 'callback_data' => "wctm_admin_order:{$orderId}:completed"],
            ];
            $keyboard[] = [
                ['text' => 'Отменить', 'callback_data' => "wctm_admin_order:{$orderId}:cancelled"],
                ['text' => 'Клиент', 'callback_data' => "wctm_customer_card:{$orderId}"],
            ];
        }

        if ($withActions) {
            $keyboard[] = [
                ['text' => '📊 Today', 'callback_data' => 'wctm_admin_today'],
            ];
        }

        return $keyboard !== [] ? ['reply_markup' => ['inline_keyboard' => $keyboard]] : [];
    }

    private function isAllowedUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }
        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return false;
        }
        return in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https', 'tg'], true);
    }
}
