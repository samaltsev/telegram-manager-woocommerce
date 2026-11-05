<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

class AutoSubscribeHandler
{
    protected AdminBotSubscriberRepository $repo;

    public function __construct()
    {
        $this->repo = new AdminBotSubscriberRepository();
    }

    public function handleStartCommand(array $telegramData): string
    {
        $chatId = $telegramData['message']['chat']['id'] ?? '';
        $username = $telegramData['message']['from']['username'] ?? '';

        if (empty($chatId)) {
            Logger::log('error', 'admin_bot', 'handleStartCommand: empty chat_id', ['has_message' => isset($telegramData['message'])]);
            return 'Error: could not detect your chat ID.';
        }

        $added = $this->repo->addSubscriber((string) $chatId, $username);
        if (!$added) {
            return 'You are already subscribed or the free Telegram chat limit has been reached.';
        }

        Logger::log('info', 'admin_bot', 'New subscriber added via /start', [
            'telegram_user_id' => $chatId,
            'telegram_username' => $username,
        ]);

        return 'You are now subscribed to Telegram order alerts.';
    }
}
