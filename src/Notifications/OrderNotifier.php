<?php
declare(strict_types=1);

namespace WCTM\Notifications;

use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\NotificationSettingsRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Queue\MessageQueue;
use WCTM\Telegram\ChatTarget;
use WCTM\Templates\TemplateParser;
use WCTM\Templates\TemplateTags;

if (!defined('ABSPATH')) {
    exit;
}

class OrderNotifier
{
    protected NotificationSettingsRepository $notifications;
    protected BotSettingsRepository $bots;
    protected TemplateSettingsRepository $templates;
    protected MessageQueue $queue;
    protected TemplateParser $parser;

    public function __construct(
        NotificationSettingsRepository $notifications,
        BotSettingsRepository $bots,
        TemplateSettingsRepository $templates,
        MessageQueue $queue,
        TemplateParser $parser
    ) {
        $this->notifications = $notifications;
        $this->bots = $bots;
        $this->templates = $templates;
        $this->queue = $queue;
        $this->parser = $parser;
    }

    protected function targets()
    {
        $raw = $this->bots->get('chat_targets', '');
        if ($this->bots->get('admin_chat_id', '')) {
            $raw = trim((string) $this->bots->get('admin_chat_id', '')) . ($raw ? "\n" . $raw : '');
        }

        return ChatTarget::parseMany($raw);
    }

    protected function queue($event, \WC_Order $order, $templateKey)
    {
        $tags = TemplateTags::forOrder($order);
        $text = $this->parser->parse((string) $this->templates->get($templateKey, ''), $tags);
        $payload = array('text' => $text);

        if ($event === 'new_order') {
            $payload['extra'] = array(
                'reply_markup' => array(
                    'inline_keyboard' => array(
                        array(
                            array('text' => 'Open order', 'url' => $tags['admin_order_url']),
                        ),
                        array(
                            array('text' => 'Set processing', 'callback_data' => 'wctm_order:' . $order->get_id() . ':processing'),
                            array('text' => 'Set completed', 'callback_data' => 'wctm_order:' . $order->get_id() . ':completed'),
                        ),
                        array(
                            array('text' => 'Cancel order', 'callback_data' => 'wctm_order:' . $order->get_id() . ':cancelled'),
                        ),
                    ),
                ),
            );
        }

        return $this->queue->enqueueForTargets($event, $payload, $this->targets(), $order->get_id());
    }

    public function queueNewOrder($orderId)
    {
        if (!$this->notifications->get('notify_new_order', 1)) {
            return;
        }

        $order = wc_get_order($orderId);
        if ($order) {
            $this->queue('new_order', $order, 'template_new_order');
        }
    }

    public function queueStatusChanged($orderId, $oldStatus, $newStatus, $order)
    {
        if (!$this->notifications->get('notify_status_changed', 1) || !$order) {
            return;
        }

        $whitelist = $this->notifications->statusWhitelist();
        if ($whitelist && !in_array($newStatus, $whitelist, true)) {
            return;
        }

        $this->queue('status_changed', $order, 'template_status_changed');
    }

    public function queuePaymentReceived($orderId, $order)
    {
        if (!$this->notifications->get('notify_payment_received', 1) || !$order) {
            return;
        }

        $this->queue('payment_received', $order, 'template_payment_received');
    }

    public function queueRefundCreated($refundId, $args)
    {
        if (!$this->notifications->get('notify_refund_created', 1)) {
            return;
        }

        $orderId = isset($args['order_id']) ? absint($args['order_id']) : 0;
        $order = $orderId ? wc_get_order($orderId) : false;
        if ($order) {
            $this->queue('refund_created', $order, 'template_refund_created');
        }
    }
}