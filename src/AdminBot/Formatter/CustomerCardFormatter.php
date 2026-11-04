<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Formatter;

if (!defined('ABSPATH')) {
    exit;
}

final class CustomerCardFormatter
{
    public function format(?array $card): string
    {
        if (!$card) {
            return "👤 Customer\n\nCustomer card is unavailable.";
        }

        $money = function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags((string) wc_price((float) $card['total_spent'])), ENT_QUOTES, 'UTF-8') : number_format((float) $card['total_spent'], 2, '.', ' ');

        return "👤 Customer card\n\n"
            . 'Name: ' . ((string) ($card['name'] ?? '') ?: '-') . "\n"
            . 'Phone: ' . ((string) ($card['phone'] ?? '') ?: '-') . "\n"
            . 'Email: ' . ((string) ($card['email'] ?? '') ?: '-') . "\n"
            . 'Telegram ID: ' . ((string) ($card['telegram_id'] ?? '') ?: '-') . "\n"
            . 'Orders: ' . (int) ($card['orders_count'] ?? 0) . "\n"
            . 'Total spent: ' . $money . "\n"
            . 'Last order: #' . (int) ($card['last_order_id'] ?? 0) . "\n"
            . 'Tags: ' . ((string) ($card['tags'] ?? '') ?: '-') . "\n"
            . 'Notes: ' . ((string) ($card['notes'] ?? '') ?: '-');
    }
}
