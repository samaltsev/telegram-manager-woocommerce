<?php
declare(strict_types=1);

namespace WCTM\Notifications;

use WC_Order;
use WCTM\Domain\Queue\Service\QueuePublisher;
use WCTM\Integrations\Telegram\Sender\TelegramMessageSender;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Logger\Logger;
use WCTM\Telegram\ChatTarget;
use WCTM\Templates\TemplateParser;
use WCTM\Templates\TemplateTags;
use WCTM\Telegram\Ui\OrderKeyboardFactory;

if (!defined('ABSPATH')) {
    exit;
}

class UnifiedNotifier
{
    protected BotSettingsRepository $bots;
    protected TelegramMessageSender $sender;
    protected ?QueuePublisher $queue;
    protected TemplateSettingsRepository $templates;
    protected TemplateParser $parser;

    public function __construct(
        BotSettingsRepository $bots,
        TelegramMessageSender $sender,
        ?QueuePublisher $queue = null,
        ?TemplateSettingsRepository $templates = null,
        ?TemplateParser $parser = null
    ) {
        $this->bots = $bots;
        $this->sender = $sender;
        $this->queue = $queue;
        $this->templates = $templates ?: new TemplateSettingsRepository();
        $this->parser = $parser ?: new TemplateParser($this->templates->getParseMode());

        $this->parser->setParseMode($this->templates->getParseMode());
    }

    protected function getSettings(): array
    {
        return (array) get_option('wctm_settings', []);
    }

    protected function getToken(string $botType = 'admin'): ?string
    {
        $key = 'bot_token';
        $token = trim((string) $this->bots->get($key));
        if ($token === '') {
            $token = trim((string) get_option($key, ''));
        }

        return $token !== '' ? $token : null;
    }

    protected function getChatId(string $botType = 'admin'): ?string
    {
        $chatId = trim((string) $this->bots->get('admin_chat_id'));
        if ($chatId === '') {
            $chatId = trim((string) $this->bots->get('chat_id'));
        }
        if ($chatId === '') {
            $chatId = trim((string) get_option('admin_chat_id', ''));
        }
        if ($chatId === '') {
            $chatId = trim((string) get_option('chat_id', ''));
        }

        return $chatId !== '' ? $chatId : null;
    }

    /**
     * Telegram inline keyboard buttons accept only a limited set of URL schemes.
     */
    protected function isAllowedInlineButtonUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        return in_array($scheme, ['http', 'https', 'tg'], true);
    }

    /**
     * Normalize incoming message payload to a plain text string.
     *
     * Supports:
     * - string
     * - array{ text?: string }
     */
    protected function normalizeMessageText($message): ?string
    {
        if (is_string($message)) {
            $message = trim($message);
            return $message !== '' ? $message : null;
        }

        if (is_array($message) && isset($message['text']) && is_string($message['text'])) {
            $text = trim($message['text']);
            return $text !== '' ? $text : null;
        }

        return null;
    }

    /**
     * @param mixed $message
     * @return array{text:string,extra:array<string,mixed>}|null
     */
    protected function normalizeMessagePayload($message): ?array
    {
        if (is_string($message)) {
            $text = trim($message);
            return $text !== '' ? ['text' => $text, 'extra' => []] : null;
        }

        if (is_array($message) && isset($message['text']) && is_string($message['text'])) {
            $text = trim($message['text']);
            if ($text === '') {
                return null;
            }

            $extra = [];
            if (isset($message['extra']) && is_array($message['extra'])) {
                $extra = $message['extra'];
            }

            return [
                'text' => $text,
                'extra' => $extra,
            ];
        }

        return null;
    }
    /**
     * @return array<string, mixed>
     */
    protected function buildOrderActionButtons(WC_Order $order): array
    {
        return (new OrderKeyboardFactory())->forAdminOrder($order, false);
    }

    protected function buildOrderContactLinksText(WC_Order $order): string
    {
        // Customer actions are rendered as inline buttons, not as raw links in text.
        return '';
    }

    /**
     * New order can be fired before line items are fully saved on some checkout flows.
     * Do not send an empty product list to Telegram; schedule one delayed retry instead.
     */
    protected function orderHasLineItems(WC_Order $order): bool
    {
        return count($order->get_items('line_item')) > 0;
    }

    protected function scheduleDelayedNewOrderNotification(WC_Order $order): void
    {
        $orderId = (int) $order->get_id();
        if ($orderId <= 0) {
            return;
        }

        $metaKey = '_wctm_new_order_items_retry_scheduled';
        if ((string) $order->get_meta($metaKey) === 'yes') {
            return;
        }

        $order->update_meta_data($metaKey, 'yes');
        $order->save_meta_data();

        if (!wp_next_scheduled('wctm_delayed_new_order_notification', [$orderId])) {
            wp_schedule_single_event(time() + 30, 'wctm_delayed_new_order_notification', [$orderId]);
        }

        Logger::log('info', 'queue', 'New order notification delayed until line items are available', [
            'order_id' => $orderId,
            'event' => 'new_order',
        ]);
    }

    protected function shouldIncludeCustomerDetails(string $templateKey): bool
    {
        return false;
    }

    /**
     * Build the confirmed Free notification layout:
     * order -> items -> total -> Open Order button.
     *
     * @param array<string,mixed> $extraTags
     */
    protected function buildFreeOrderMessage(WC_Order $order, string $templateKey, array $extraTags = []): string
    {
        $tags = TemplateTags::forOrder($order);
        foreach ($extraTags as $key => $value) {
            $tags[$key] = $value;
        }

        $orderNumber = (string) ($tags['order_number'] ?? $order->get_order_number());
        $itemsCount = (string) ($tags['items_count'] ?? '0');
        $itemsShort = trim((string) ($tags['items_short'] ?? $tags['order_items_text'] ?? ''));
        $orderTotal = (string) ($tags['order_total'] ?? '');

        // Never show "0 items / Items not available" for a new order in Free notifications.
        // New-order sending is delayed/retried until line items are available.
        if ($itemsShort === '' && $templateKey !== 'template_new_order') {
            $itemsShort = __('Items not available', 'telegram-manager-woocommerce');
        }

        // Only new-order notifications may contain optional remaining stock.
        // Product stock is read live after the order-creation request has finished.
        if ($templateKey === 'template_new_order' && !empty($this->getSettings()['show_new_order_stock'])) {
            $itemsShort = $this->formatNewOrderItemsWithStock($order);
        }

        $itemsBlock = sprintf(__('📦 Items: %s', 'telegram-manager-woocommerce'), $itemsCount);
        if ($itemsShort !== '') {
            $itemsBlock .= "\n" . $itemsShort;
        }

        if ($templateKey === 'template_new_order') {
            return implode("\n\n", array_filter([
                sprintf(__('🛒 Order #%s', 'telegram-manager-woocommerce'), $orderNumber),
                $itemsBlock,
                sprintf(__('💰 %s', 'telegram-manager-woocommerce'), $orderTotal),
            ], static fn($line): bool => trim((string) $line) !== ''));
        }

        if ($templateKey === 'template_refund_created') {
            return implode("\n\n", array_filter([
                sprintf(__('💸 Refund for order #%s', 'telegram-manager-woocommerce'), $orderNumber),
                $itemsBlock,
                sprintf(__('💰 %s', 'telegram-manager-woocommerce'), $orderTotal),
            ], static fn($line): bool => trim((string) $line) !== ''));
        }

        if ($templateKey === 'template_payment_received') {
            return implode("\n\n", array_filter([
                sprintf(__('💳 Payment received for order #%s', 'telegram-manager-woocommerce'), $orderNumber),
                $itemsBlock,
                sprintf(__('💰 %s', 'telegram-manager-woocommerce'), $orderTotal),
            ], static fn($line): bool => trim((string) $line) !== ''));
        }

        if ($templateKey === 'template_status_changed') {
            $newStatus = trim((string) ($tags['new_order_status'] ?? $tags['order_status'] ?? ''));
            $oldStatus = trim((string) ($tags['old_order_status'] ?? ''));

            if ($newStatus !== '' && $oldStatus !== '') {
                $statusLine = sprintf(
                    __('Status changed from “%1$s” to “%2$s”.', 'telegram-manager-woocommerce'),
                    $oldStatus,
                    $newStatus
                );
            } elseif ($newStatus !== '') {
                $statusLine = sprintf(__('Status: %s', 'telegram-manager-woocommerce'), $newStatus);
            } else {
                $statusLine = __('Order status changed.', 'telegram-manager-woocommerce');
            }

            // Confirmed Free status layout:
            // Order -> status message -> items -> total -> Open Order button.
            return implode("\n\n", array_filter([
                sprintf(__('Order #%s', 'telegram-manager-woocommerce'), $orderNumber),
                '💬 ' . $statusLine,
                $itemsBlock,
                sprintf(__('💰 %s', 'telegram-manager-woocommerce'), $orderTotal),
            ], static fn($line): bool => trim((string) $line) !== ''));
        }

        return $this->buildOrderMessage($templateKey, $order, $extraTags) ?: '';
    }

    /**
     * Append a live stock snapshot for products managing inventory.
     * Variations may share stock with their parent product.
     */
    protected function formatNewOrderItemsWithStock(WC_Order $order): string
    {
        $lines = [];
        foreach ($order->get_items('line_item') as $item) {
            if (!$item instanceof \WC_Order_Item_Product) {
                continue;
            }
            $lines[] = $item->get_name() . ' ×' . wc_stock_amount($item->get_quantity());
            $product = $item->get_product();
            if (!$product instanceof \WC_Product) {
                continue;
            }
            // WooCommerce may manage stock on the variation or its parent.
            $stockProductId = (int) $product->get_stock_managed_by_id();
            $stockProduct = $stockProductId > 0 ? wc_get_product($stockProductId) : $product;
            if (!$stockProduct instanceof \WC_Product || !$stockProduct->managing_stock()) {
                continue;
            }
            $quantity = $stockProduct->get_stock_quantity();
            if ($quantity === null) {
                continue;
            }
            $lines[] = '↳ ' . sprintf(
                (str_starts_with(determine_locale(), 'ru') ? 'В наличии: %s' : (str_starts_with(determine_locale(), 'uk') ? 'У наявності: %s' : 'In stock: %s')),
                wc_stock_amount($quantity)
            );
        }
        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $extraTags
     * @return array{text:string, extra:array<string,mixed>}|null
     */
    protected function buildOrderNotificationPayload(string $templateKey, WC_Order $order, array $extraTags = []): ?array
    {
        $text = $this->buildFreeOrderMessage($order, $templateKey, $extraTags);
        if (trim($text) === '') {
            return null;
        }

        return [
            'text' => $text,
            'extra' => $this->buildOrderActionButtons($order),
        ];
    }

    /**
     * Send message safely to explicit chat ID.
     */
    public function sendToChat(
        string $chatId,
        $message,
        array $meta = [],
        bool $async = false,
        string $botType = 'admin'
    ): bool {
        $chatId = trim($chatId);
        $payload = $this->normalizeMessagePayload($message);

        if ($chatId === '') {
            Logger::log('error', $botType . '_bot', 'chat_id not set', array_merge($meta, [
                'bot_type' => $botType,
            ]));
            return false;
        }

        if ($payload === null) {
            Logger::log('error', $botType . '_bot', 'Invalid or empty message payload', array_merge($meta, [
                'chat_id' => $chatId,
                'bot_type' => $botType,
                'message_type' => gettype($message),
            ]));
            return false;
        }

        if (!$this->queue) {
            Logger::log('error', $botType . '_bot', 'Queue publisher is unavailable; direct Telegram send is disabled in production mode', array_merge($meta, [
                'chat_id' => $chatId,
                'bot_type' => $botType,
            ]));
            return false;
        }

        $extra = $payload['extra'];
        $extra['bot_type'] = sanitize_key($botType ?: 'admin');

        $event = isset($meta['event']) ? sanitize_key((string) $meta['event']) : 'telegram_message';
        $orderId = isset($meta['order_id']) ? absint($meta['order_id']) : null;

        $queued = $this->queue->publishTelegramForTargets(
            $event,
            [
                'text' => $payload['text'],
                'extra' => $extra,
            ],
            [new ChatTarget($chatId)],
            $orderId
        );

        Logger::log('info', 'queue', 'Notification queued', array_merge($meta, [
            'chat_id' => $chatId,
            'bot_type' => $botType,
            'queued' => $queued,
        ]));

        return $queued > 0;
    }

    /**
     * Send message to admin chat.
     */
    protected function sendToAdmin($message, array $meta = [], bool $async = false): bool
    {
        $adminChatId = $this->getChatId('admin');
        if ($adminChatId === null) {
            Logger::log('error', 'admin_bot', 'Admin chat_id not set', $meta);
            return false;
        }

        return $this->sendToChat($adminChatId, $message, $meta, $async, 'admin');
    }

    /**
     * Build order message from configured template.
     */
    protected function buildOrderMessage(string $templateKey, WC_Order $order, array $extraTags = []): ?string
    {
        try {
            $context = TemplateTags::forOrder($order);

            foreach ($extraTags as $key => $value) {
                $context[$key] = $value;
            }

            $template = $this->templates->get($templateKey);
            if (!is_string($template) || trim($template) === '') {
                Logger::log('error', 'admin_bot', 'Order template not found or empty', [
                    'template_key' => $templateKey,
                    'order_id' => $order->get_id(),
                ]);
                return null;
            }

            return $this->parser->parse($template, $context);
        } catch (\Throwable $e) {
            Logger::log('error', 'admin_bot', 'Failed to build order message', [
                'template_key' => $templateKey,
                'order_id' => $order->get_id(),
                'error_message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * New order notification.
     */
    public function notifyNewOrder(WC_Order $order, bool $async = false): void
    {
        $settings = $this->getSettings();

        $enabled = array_key_exists('notify_new_order', $settings)
            ? !empty($settings['notify_new_order'])
            : true;

        if (!$enabled) {
            return;
        }

        if (!$this->orderHasLineItems($order)) {
            $this->scheduleDelayedNewOrderNotification($order);
            return;
        }

        $order->delete_meta_data('_wctm_new_order_items_retry_scheduled');
        $order->save_meta_data();

        $payload = $this->buildOrderNotificationPayload('template_new_order', $order);
        if ($payload === null) {
            return;
        }

        $this->sendToAdmin($payload, [
            'event' => 'new_order',
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
        ], $async);
    }

    /**
     * Order status changed notification.
     */
    public function notifyOrderStatusChange(
        WC_Order $order,
        string $oldStatus,
        string $newStatus,
        bool $async = false
    ): void {
        $settings = $this->getSettings();

        $enabled = array_key_exists('notify_status_changed', $settings)
            ? !empty($settings['notify_status_changed'])
            : true;

        if (!$enabled) {
            return;
        }

        $oldStatus = sanitize_key($oldStatus);
        $newStatus = sanitize_key($newStatus);

        if (in_array($oldStatus, ['draft', 'auto-draft', 'checkout-draft', 'trash'], true)
            || in_array($newStatus, ['draft', 'auto-draft', 'checkout-draft', 'trash'], true)) {
            return;
        }

        // A full refund may invoke both woocommerce_order_refunded and a status
        // transition. Keep the refund-created message; suppress the related status
        // message regardless of hook ordering. Manual changes to refunded remain visible.
        if ($newStatus === 'refunded' && $this->hasRecentOrderRefund($order)) {
            return;
        }

        // WooCommerce may fire payment_complete before or after processing transition.
        // In either order only one payment-related notification should be queued.
        if ($newStatus === 'processing' && get_transient('wctm_paid_' . $order->get_id())) {
            return;
        }

        $eventKey = 'notify_status_' . $newStatus;
        $defaults = [
            'completed' => true, 'cancelled' => true, 'refunded' => true,
        ];
        $enabledForStatus = array_key_exists($eventKey, $settings)
            ? !empty($settings[$eventKey])
            : isset($defaults[$newStatus]);
        if (!$enabledForStatus) {
            return;
        }
        // Repeated status hooks for the same transition must not queue duplicate jobs.
        $dedupeKey = 'wctm_ord_' . md5($order->get_id() . '|status|' . $oldStatus . '|' . $newStatus);
        if (get_transient($dedupeKey)) {
            return;
        }

        $humanNewStatus = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name($newStatus)
            : $newStatus;

        $humanOldStatus = function_exists('wc_get_order_status_name')
            ? wc_get_order_status_name($oldStatus)
            : $oldStatus;

        $payload = $this->buildOrderNotificationPayload('template_status_changed', $order, [
            'order_status' => $humanNewStatus,
            'old_order_status' => $humanOldStatus,
            'new_order_status' => $humanNewStatus,
        ]);

        if ($payload === null) {
            return;
        }

        $queued = $this->sendToAdmin($payload, [
            'event' => 'order_status_changed',
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ], $async);
        if ($queued) {
            set_transient($dedupeKey, 1, 90);
            // Payment completion commonly triggers processing; avoid sending both alerts.
            if ($newStatus === 'processing') {
                set_transient('wctm_paid_processing_' . $order->get_id(), 1, 90);
            }
        }
    }

    /**
     * Payment received notification.
     */
    public function notifyPaymentReceived(WC_Order $order, bool $async = false): void
    {
        $settings = $this->getSettings();

        $enabled = array_key_exists('notify_payment_received', $settings)
            ? !empty($settings['notify_payment_received'])
            : true;

        if (!$enabled) {
            return;
        }

        // Do not send a second alert if the payment already triggered a processing notice.
        if (get_transient('wctm_paid_processing_' . $order->get_id())) {
            return;
        }
        $dedupeKey = 'wctm_paid_' . $order->get_id();
        if (get_transient($dedupeKey)) {
            return;
        }
        $payload = $this->buildOrderNotificationPayload('template_payment_received', $order);

        if ($payload === null) {
            return;
        }

        $queued = $this->sendToAdmin($payload, [
            'event' => 'payment_received',
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
        ], $async);
        if ($queued) {
            set_transient($dedupeKey, 1, DAY_IN_SECONDS);
        }
    }

    /**
     * Only collapse a refunded status transition when an actual recent refund
     * object exists. This keeps manual status edits and historical refunds visible.
     */
    private function hasRecentOrderRefund(WC_Order $order): bool
    {
        foreach ($order->get_refunds() as $refund) {
            if (!$refund instanceof \WC_Order_Refund) {
                continue;
            }
            $date = $refund->get_date_created();
            if ($date && abs(time() - $date->getTimestamp()) <= 120) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refund created notification.
     */
    public function notifyRefundCreated(WC_Order $order, int $refundId = 0, bool $async = false): void
    {
        $settings = $this->getSettings();

        $enabled = array_key_exists('notify_refund_created', $settings)
            ? !empty($settings['notify_refund_created'])
            : true;

        if (!$enabled) {
            return;
        }

        // The same refund hook can fire twice; a distinct WC refund ID remains
        // a distinct business event (partial refunds are never merged).
        $refundKey = $refundId > 0 ? 'wctm_refund_event_' . $refundId : '';
        if ($refundKey !== '' && get_transient($refundKey)) {
            return;
        }

        $payload = $this->buildOrderNotificationPayload('template_refund_created', $order, [
            'refund_id' => $refundId > 0 ? (string) $refundId : '',
        ]);

        if ($payload === null) {
            return;
        }

        $queued = $this->sendToAdmin($payload, [
            'event' => 'refund_created',
            'order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'refund_id' => $refundId,
        ], $async);
        if ($queued && $refundKey !== '') {
            set_transient($refundKey, 1, DAY_IN_SECONDS);
        }
    }

}
