<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot;

use WCTM\AdminBot\AdminBotSubscriberRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleStartCommandHandler
{
    public function __construct(
        private AdminBotSubscriberRepositoryInterface $subscribers
    ) {
    }

    /**
     * @return array{pref_key:string,pref_value:bool,message:string,keyboard:array<int,array<int,array<string,string>>>}
     */
    public function handle(string $chatId, string $username): array
    {
        $this->subscribers->addSubscriber($chatId, $username);
        $this->subscribers->updateStatusByChatId($chatId, 'subscribed');

        return [
            'pref_key' => 'paused',
            'pref_value' => false,
            'message' => "👋 Telegram bot connected\n\nI will notify you about orders, payments and customer messages.",
            'keyboard' => [
                [['text' => '📦 Orders'], ['text' => '💬 Messages']],
                [['text' => '💳 Payments'], ['text' => '📊 Today']],
                [['text' => '⚙️ Settings']],
            ],
        ];
    }
}
