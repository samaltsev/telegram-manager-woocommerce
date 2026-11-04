<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use WCTM\Logger\Logger;
use WCTM\Common\MessageSender;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;

if (!defined('ABSPATH')) exit;

class AdminBotHandler
{
    protected AdminBotSubscriberRepository $repo;
    protected BotOptionRepository $botOptions;
    protected MessageSender $sender;

    public function __construct(
        AdminBotSubscriberRepository $repo,
        BotOptionRepository $botOptions,
        MessageSender $sender
    ) {
        $this->repo = $repo;
        $this->botOptions = $botOptions;
        $this->sender = $sender;
    }

    public function sendNotification(string $message): void
    {
        $subscribers = $this->repo->all();
        Logger::debugStep('AdminBot sending notifications', ['count' => count($subscribers)]);

        foreach ($subscribers as $sub) {
            $chatId = $sub->chatId();
            if (empty($chatId)) {
                continue;
            }

            $this->sender->send('admin', [['telegram_user_id' => $chatId]], $message, [
                'bot_type' => 'admin',
            ]);

            Logger::debugStep('Message queued for admin', ['telegram_user_id' => $chatId]);
        }
    }
}