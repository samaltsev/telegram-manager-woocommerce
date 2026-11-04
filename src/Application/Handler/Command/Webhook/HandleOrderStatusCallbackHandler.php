<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\Webhook;

use WCTM\Common\MessageSender;
use WCTM\Integrations\Telegram\Client\TelegramClient;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;
use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class HandleOrderStatusCallbackHandler
{
    protected BotOptionRepository $botOptions;
    protected TelegramClient $client;
    protected ?MessageSender $messageSender;

    public function __construct(BotOptionRepository $botOptions, TelegramClient $client, ?MessageSender $messageSender = null)
    {
        $this->botOptions = $botOptions;
        $this->client = $client;
        $this->messageSender = $messageSender;
    }

    public function handle(array $callback)
    {
        $data = (string) ($callback['data'] ?? '');

        if (strpos($data, 'wctm_customer:') === 0) {
            return $this->handleCustomerContact($callback, $data);
        }

        if (strpos($data, 'wctm_order:') !== 0) {
            return false;
        }

        $parts = explode(':', $data);
        $orderId = isset($parts[1]) ? absint($parts[1]) : 0;
        $status = isset($parts[2]) ? sanitize_key($parts[2]) : '';
        $allowed = array('processing', 'completed', 'cancelled');
        $token = $this->botOptions->getDefaultToken();

        if (!$orderId || !in_array($status, $allowed, true) || !function_exists('wc_get_order')) {
            $this->answerCallbackImmediately($token, $callback, 'Invalid action.');
            return true;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->answerCallbackImmediately($token, $callback, 'Order not found.');
            return true;
        }

        $order->update_status($status, 'Status changed from Telegram.', true);
        Logger::log('info', 'webhook', 'Order status changed from Telegram', array(
            'event' => 'callback_query',
            'order_id' => $orderId,
            'chat_id' => $callback['from']['id'] ?? '',
            'request_payload' => $callback,
            'response_payload' => array('status' => $status),
        ));

        $this->answerCallbackImmediately($token, $callback, 'Order #' . $order->get_order_number() . ' -> ' . wc_get_order_status_name($status));

        return true;
    }

    protected function handleCustomerContact(array $callback, string $data): bool
    {
        $parts = explode(':', $data);
        $orderId = isset($parts[1]) ? absint($parts[1]) : 0;
        $action = isset($parts[2]) ? sanitize_key($parts[2]) : '';
        $token = $this->botOptions->getDefaultToken();

        if (!$orderId || !in_array($action, ['call', 'write'], true) || !function_exists('wc_get_order')) {
            $this->answerCallbackImmediately($token, $callback, 'Invalid action.');
            return true;
        }

        $order = wc_get_order($orderId);
        if (!$order) {
            $this->answerCallbackImmediately($token, $callback, 'Order not found.');
            return true;
        }

        $phone = trim((string) $order->get_billing_phone());
        $email = trim((string) $order->get_billing_email());
        $name = trim((string) ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()));
        $name = $name !== '' ? $name : 'Customer';

        if ($action === 'call') {
            $text = $phone !== ''
                ? ("Customer phone ({$name}): {$phone}")
                : 'Customer phone is not set.';

            $this->sendMessageToCallbackChat($token, $callback, $text);
            $this->answerCallbackImmediately($token, $callback, $phone !== '' ? 'Phone sent to chat.' : 'Phone is not set.');
            return true;
        }

        $text = '';
        if ($phone !== '' && $email !== '') {
            $text = "Customer contacts ({$name}):\nPhone: {$phone}\nEmail: {$email}";
        } elseif ($phone !== '') {
            $text = "Customer contact ({$name}):\nPhone: {$phone}";
        } elseif ($email !== '') {
            $text = "Customer contact ({$name}):\nEmail: {$email}";
        } else {
            $text = 'Customer contact details are not set.';
        }

        $this->sendMessageToCallbackChat($token, $callback, $text);
        $this->answerCallbackImmediately($token, $callback, 'Contacts sent to chat.');

        return true;
    }

    /**
     * Intentional synchronous exception to queue-only message delivery.
     * Telegram requires callback acknowledgements immediately to stop the
     * loading indicator; this method does not deliver a chat message.
     */
    protected function answerCallbackImmediately($token, array $callback, $text)
    {
        $this->client->answerCallbackQuery($token, array(
            'callback_query_id' => $callback['id'] ?? '',
            'text' => (string) $text,
            'show_alert' => false,
        ));
    }

    protected function sendMessageToCallbackChat(string $token, array $callback, string $text): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        if ($chatId === '' || trim($text) === '') {
            return;
        }

        if ($this->messageSender === null) {
            Logger::log('error', 'webhook', 'Queue sender unavailable for callback message', [
                'chat_id' => $chatId,
            ]);
            return;
        }

        $this->messageSender->send('admin', [['telegram_user_id' => $chatId]], $text, [
            'bot_type' => 'admin',
        ]);
    }
}
