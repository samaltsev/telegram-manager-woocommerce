<?php
declare(strict_types=1);

namespace WCTM\Notifications;

use WCTM\Notifications\UnifiedNotifier;

if (!defined('ABSPATH')) { exit; }

/**
 * Stock/status notifications for simple products and variations.
 * Uses the same persistent Telegram queue as WooCommerce order notifications.
 */
final class ProductStatusNotifier
{
    /** @var array<int,array{product:\WC_Product,previous:int,current:int}> */
    private array $pendingStock = [];
    private bool $flushRegistered = false;

    public function __construct(private UnifiedNotifier $notifier) {}

    private function lang(string $ru, string $uk, string $en): string
    {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        return str_starts_with($locale, 'ru') ? $ru : (str_starts_with($locale, 'uk') ? $uk : $en);
    }

    private function enabled(string $key): bool
    {
        $settings = (array) get_option('wctm_settings', []);
        return !empty($settings[$key]);
    }

    private function product(int $productId): ?\WC_Product
    {
        $product = wc_get_product($productId);
        return $product instanceof \WC_Product ? $product : null;
    }

    public function stockStatusChanged(int $productId, string $stockStatus, $product = null): void
    {
        $map = [
            'instock' => ['notify_product_instock', '✅', $this->lang('В наличии', 'У наявності', 'In stock')],
            'outofstock' => ['notify_product_outofstock', '❌', $this->lang('Нет в наличии', 'Немає в наявності', 'Out of stock')],
            'onbackorder' => ['notify_product_onbackorder', '📦', $this->lang('Под заказ', 'Під замовлення', 'On backorder')],
        ];
        if (!isset($map[$stockStatus])) { return; }
        [$setting, $icon, $label] = $map[$stockStatus];
        if (!$this->enabled($setting)) { return; }
        $product = $product instanceof \WC_Product ? $product : $this->product($productId);
        if (!$product) { return; }
        $this->send($product, 'product_stock_' . sanitize_key($stockStatus), $icon . ' ' . $label);
    }

    public function lowStock($product): void
    {
        $this->inventoryEvent($product, 'notify_product_lowstock', 'product_low_stock', '⚠️ ' . $this->lang('Низкий остаток', 'Низький залишок', 'Low stock'));
    }

    public function noStock($product): void
    {
        $this->inventoryEvent($product, 'notify_product_outofstock', 'product_stock_outofstock', '❌ ' . $this->lang('Нет в наличии', 'Немає в наявності', 'Out of stock'));
    }

    public function backorder($args): void
    {
        if (!$this->enabled('notify_product_onbackorder') || !is_array($args)) { return; }
        $product = $args['product'] ?? null;
        if (!$product instanceof \WC_Product) { return; }
        $this->send($product, 'product_backorder_event', '📦 ' . $this->lang('Оформлен предзаказ', 'Оформлено передзамовлення', 'Backorder placed'));
    }

    private function inventoryEvent($product, string $setting, string $event, string $title): void
    {
        if (!$this->enabled($setting)) { return; }
        if (is_numeric($product)) { $product = $this->product((int) $product); }
        if (!$product instanceof \WC_Product) { return; }
        $this->send($product, $event, $title);
    }

    /**
     * Buffer stock updates until WooCommerce identifies their order. Stock changes
     * not associated with an order are emitted as individual manual adjustments.
     */
    public function quantityChanged($product): void
    {
        if (!$this->enabled('notify_product_quantity')) { return; }
        if (!$product instanceof \WC_Product || !$product->managing_stock()) { return; }
        $current = $product->get_stock_quantity();
        if ($current === null) { return; }
        $key = '_wctm_last_stock_qty';
        $previous = $product->get_meta($key, true);
        if ($previous !== '' && is_numeric($previous) && (int) $previous !== (int) $current) {
            $id = $product->get_id();
            if (isset($this->pendingStock[$id])) {
                $this->pendingStock[$id]['current'] = (int) $current;
            } else {
                $this->pendingStock[$id] = [
                    'product' => $product, 'previous' => (int) $previous, 'current' => (int) $current,
                ];
            }
            if (!$this->flushRegistered) {
                $this->flushRegistered = true;
                add_action('shutdown', [$this, 'flushManualStock'], 120);
            }
        }
        if ((string) $previous !== (string) $current) {
            $product->update_meta_data($key, (string) $current);
            $product->save_meta_data();
        }
    }

    /**
     * Both actions run after WooCommerce has adjusted an order's stock.
     * The order-item mapping prevents unrelated stock changes being absorbed.
     */
    public function orderStockChanged($order): void
    {
        if (!$this->enabled('notify_product_quantity') || !$order instanceof \WC_Order) { return; }
        $matched = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) { continue; }
            $product = $item->get_product();
            if (!$product instanceof \WC_Product) { continue; }
            $managedId = (int) $product->get_stock_managed_by_id();
            if ($managedId <= 0) { $managedId = $product->get_id(); }
            if (!isset($this->pendingStock[$managedId])) { continue; }
            $matched[$managedId] = $this->pendingStock[$managedId];
            unset($this->pendingStock[$managedId]);
        }
        if (!$matched) { return; }
        $title = $this->lang('📦 Изменение остатков — заказ #', '📦 Зміна залишків — замовлення #', '📦 Stock changes — order #');
        $unit = $this->lang('шт.', 'шт.', 'pcs');
        $lines = [$title . $order->get_order_number(), ''];
        foreach ($matched as $row) {
            $lines[] = $row['product']->get_name() . ': ' . $row['previous'] . ' → ' . $row['current'] . ' ' . $unit;
        }
        $url = $order->get_edit_order_url();
        $button = $this->lang('📋 Открыть заказ', '📋 Відкрити замовлення', '📋 Open order');
        $this->queueStockMessage(implode("\n", $lines), 'order_stock_changed', $order->get_id(), $url, $button);
    }

    public function flushManualStock(): void
    {
        if (!$this->enabled('notify_product_quantity')) { $this->pendingStock = []; return; }
        foreach ($this->pendingStock as $row) {
            if ($row['previous'] === $row['current']) { continue; }
            $title = $this->lang('📊 Изменение остатка', '📊 Зміна залишку', '📊 Stock quantity changed');
            $name = $this->lang('Товар: ', 'Товар: ', 'Product: ');
            $unit = $this->lang('шт.', 'шт.', 'pcs');
            $product = $row['product'];
            $text = $title . "\n" . $name . $product->get_name()
                . "\n" . $row['previous'] . ' → ' . $row['current'] . ' ' . $unit;
            $editId = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $this->queueStockMessage($text, 'product_quantity_changed', 0,
                admin_url('post.php?post=' . (int) $editId . '&action=edit'),
                $this->lang('📦 Открыть товар', '📦 Відкрити товар', '📦 Open product'));
        }
        $this->pendingStock = [];
    }

    private function queueStockMessage(string $text, string $event, int $orderId, string $url, string $label): void
    {
        $settings = (array) get_option('wctm_settings', []);
        $chat = trim((string) ($settings['admin_chat_id'] ?? $settings['chat_id'] ?? get_option('chat_id', '')));
        if ($chat === '') { return; }
        $this->notifier->sendToChat($chat, [
            'text' => $text,
            'extra' => ['reply_markup' => ['inline_keyboard' => [[
                ['text' => $label, 'url' => $url]
            ]]]],
        ], ['event' => $event, 'order_id' => $orderId], true);
    }

    /**
     * Track post publication changes separately from stock availability.
     * Only WooCommerce products (not unrelated post types).
     */
    public function publicationChanged(string $new, string $old, $post): void
    {
        if (!$this->enabled('notify_product_publication') || $new === $old || !($post instanceof \WP_Post)) { return; }
        if (!in_array($post->post_type, ['product', 'product_variation'], true)) { return; }
        // Ignore revision/autosave transitions and initial automatic drafts.
        if (in_array($new, ['auto-draft', 'inherit'], true) || $old === 'auto-draft') { return; }
        $product = $this->product((int) $post->ID);
        if (!$product) { return; }
        $this->send($product, 'product_publication_' . sanitize_key($new),
            '📝 ' . $this->lang('Статус публикации изменён', 'Статус публікації змінено', 'Product publication status changed'),
            sprintf(__('%1$s → %2$s', 'telegram-manager-woocommerce'), $old, $new));
    }

    private function send(\WC_Product $product, string $event, string $title, string $details = ''): void
    {
        $id = $product->get_id();
        // Deduplicate simultaneous WooCommerce stock-status and threshold hooks.
        $dedupeKey = 'wctm_prod_' . md5($id . '|' . $event . '|' . (string) $product->get_stock_quantity());
        if (get_transient($dedupeKey)) { return; }

        $lines = [
            $title,
            $this->lang('Товар:', 'Товар:', 'Product:') . ' ' . $product->get_name(),
            'ID: ' . $id,
        ];
        if ($product->get_sku() !== '') {
            $lines[] = 'SKU: ' . $product->get_sku();
        }
        if ($product->managing_stock()) {
            $qty = $product->get_stock_quantity();
            $lines[] = $this->lang('Остаток:', 'Залишок:', 'Quantity:') . ' ' . ($qty === null ? '—' : (string) $qty);
        }
        if ($product->is_type('variation')) {
            $lines[] = $this->lang('ID родительского товара:', 'ID батьківського товару:', 'Parent product ID:') . ' ' . $product->get_parent_id();
        }
        if ($details !== '') { $lines[] = $details; }
        $url = admin_url('post.php?post=' . ($product->is_type('variation') ? $product->get_parent_id() : $id) . '&action=edit');
        $chat = trim((string) get_option('chat_id', ''));
        if ($chat === '') {
            $settings = (array) get_option('wctm_settings', []);
            $chat = trim((string) ($settings['admin_chat_id'] ?? $settings['chat_id'] ?? ''));
        }
        if ($chat === '') { return; }
        $sent = $this->notifier->sendToChat($chat, [
            'text' => implode("\n", $lines),
            'extra' => ['reply_markup' => ['inline_keyboard' => [[
                ['text' => $this->lang('📦 Открыть товар', '📦 Відкрити товар', '📦 Open product'), 'url' => $url]
            ]]]],
        ], ['event' => $event, 'product_id' => $id], true);
        if ($sent) { set_transient($dedupeKey, 1, 60); }
    }
}
