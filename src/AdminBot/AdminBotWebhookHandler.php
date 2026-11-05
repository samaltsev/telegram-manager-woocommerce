<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use WCTM\Common\MessageSender;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;
use WCTM\Licensing\FeatureGate;
use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal Free webhook handler.
 *
 * The Free plugin is focused on WooCommerce order notifications. It does not
 * expose order-management, reports, customer CRM or inline action commands in
 * Telegram. Runtime replies are still queued through MessageSender, so delivery
 * uses the same job → worker → Telegram → log chain as order notifications.
 */
class AdminBotWebhookHandler
{
    public function __construct(
        private BotOptionRepository $botOptions,
        $telegramClient,
        private Logger $logger,
        private AdminBotSubscriberRepositoryInterface $subscribers,
        private MessageSender $messageSender,
        private FeatureGate $features
    ) {
    }

    /**
     * @param array<string,mixed> $update
     */
    public function handle(array $update): void
    {
        $message = isset($update['message']) && is_array($update['message']) ? $update['message'] : [];
        $chat = isset($message['chat']) && is_array($message['chat']) ? $message['chat'] : [];
        $chatId = isset($chat['id']) ? trim((string) $chat['id']) : '';
        $text = isset($message['text']) ? trim((string) $message['text']) : '';

        if ($chatId === '') {
            return;
        }

        if (!$this->isAllowedAdminChat($chatId)) {
            $this->queueReply(
                $chatId,
                __('This bot is configured for another admin chat. Add this chat ID in plugin settings to receive WooCommerce notifications here.', 'telegram-manager-woocommerce')
            );
            return;
        }

        $command = strtolower(strtok($text, ' ') ?: '');

        if (in_array($command, ['/start', 'start'], true)) {
            $this->queueReply($chatId, $this->welcomeMessage());
            return;
        }

        if (in_array($command, ['/help', 'help'], true)) {
            $this->queueReply($chatId, $this->helpMessage());
            return;
        }

        if ($this->isProCommand($command) && !$this->features->allows(FeatureGate::ADMIN_BOT_COMMANDS)) {
            $this->queueReply(
                $chatId,
                __('Telegram bot management commands are a Pro feature. Free keeps this chat active for WooCommerce order notifications only.', 'telegram-manager-woocommerce')
            );
            return;
        }

        $this->queueReply(
            $chatId,
            __('Telegram Control Center for WooCommerce Free sends WooCommerce order notifications to this chat. Configure notification types in WordPress admin: Telegram Control Center → Notifications.', 'telegram-manager-woocommerce')
        );
    }

    private function isAllowedAdminChat(string $chatId): bool
    {
        $configured = trim($this->botOptions->getAdminChatId());
        return $configured === '' || $configured === $chatId;
    }

    private function isProCommand(string $command): bool
    {
        return in_array($command, [
            '/today',
            'today',
            '/orders',
            'orders',
            '/revenue',
            'revenue',
            '/customers',
            'customers',
            '/lowstock',
            'lowstock',
            '/low_stock',
            'low_stock',
        ], true);
    }

    private function welcomeMessage(): string
    {
        $site = wp_parse_url(home_url(), PHP_URL_HOST) ?: get_bloginfo('name');

        return sprintf(
            "✅ %s\n\n%s\n%s: %s",
            __('Telegram Control Center for WooCommerce', 'telegram-manager-woocommerce'),
            __('Bot connection is active.', 'telegram-manager-woocommerce'),
            __('Site', 'telegram-manager-woocommerce'),
            (string) $site
        );
    }

    private function helpMessage(): string
    {
        return implode("\n", [
            __('Telegram Control Center for WooCommerce Free', 'telegram-manager-woocommerce'),
            '',
            __('This chat receives WooCommerce order notifications:', 'telegram-manager-woocommerce'),
            '• ' . __('New order', 'telegram-manager-woocommerce'),
            '• ' . __('Completed order', 'telegram-manager-woocommerce'),
            '• ' . __('Cancelled order', 'telegram-manager-woocommerce'),
            '• ' . __('Refund alerts', 'telegram-manager-woocommerce'),
            '',
            __('Use WordPress admin to change settings.', 'telegram-manager-woocommerce'),
        ]);
    }

    private function queueReply(string $chatId, string $message): void
    {
        $this->messageSender->send('admin', [[
            'telegram_user_id' => $chatId,
        ]], $message, [
            'event' => 'free_admin_bot_reply',
        ]);

        Logger::log('info', 'queue', 'Free Telegram bot reply queued', [
            'event' => 'free_admin_bot_reply',
            'chat_id' => $chatId,
        ]);
    }
}
