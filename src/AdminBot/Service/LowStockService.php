<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Service;

if (!defined('ABSPATH')) {
    exit;
}

final class LowStockService
{
    /** @return array<int, \WC_Product> */
    public function products(int $threshold = 5): array
    {
        if (!function_exists('wc_get_products')) {
            return [];
        }
        $threshold = max(0, $threshold);
        return (array) wc_get_products([
            'limit' => 20,
            'status' => 'publish',
            'manage_stock' => true,
            'stock_quantity' => $threshold,
            'orderby' => 'stock_quantity',
            'order' => 'ASC',
        ]);
    }
}
