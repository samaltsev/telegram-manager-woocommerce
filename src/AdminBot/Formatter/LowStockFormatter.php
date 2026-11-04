<?php
declare(strict_types=1);

namespace WCTM\AdminBot\Formatter;

if (!defined('ABSPATH')) {
    exit;
}

final class LowStockFormatter
{
    public function format(array $products): string
    {
        if ($products === []) {
            return "📦 Low stock\n\nNo low stock products found.";
        }

        $lines = ['📦 Low stock', ''];
        foreach ($products as $product) {
            if (!is_object($product) || !method_exists($product, 'get_name')) {
                continue;
            }
            $sku = method_exists($product, 'get_sku') ? (string) $product->get_sku() : '';
            $qty = method_exists($product, 'get_stock_quantity') ? (string) $product->get_stock_quantity() : '-';
            $lines[] = '• ' . $product->get_name() . ($sku !== '' ? ' / SKU: ' . $sku : '') . ' — qty: ' . $qty;
        }

        return implode("\n", $lines);
    }
}
