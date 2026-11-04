<?php
declare(strict_types=1);
namespace WCTM\Common;

use WCTM\Domain\Queue\Service\QueuePublisher;
use WCTM\Logger\Logger;
use WCTM\Telegram\ChatTarget;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Single outbound channel for runtime Telegram messages.
 *
 * This class intentionally does not call Telegram directly. Admin Bot replies,
 * command responses and callback follow-ups are queued and then delivered by the
 * worker, so all retries, delivery logs and duplicate protection stay consistent.
 */
class MessageSender
{
    private QueuePublisher $queue;

    public function __construct(QueuePublisher $queue)
    {
        $this->queue = $queue;
    }

    /**
     * @param array<int,array<string,mixed>> $subscribers
     * @param array<string,mixed> $extra
     */
    public function send(string $botType, array $subscribers, string $message, array $extra = []): void
    {
        $targets = [];

        foreach ($subscribers as $sub) {
            $chatId = isset($sub['telegram_user_id']) ? trim((string) $sub['telegram_user_id']) : '';
            if ($chatId === '') {
                continue;
            }

            $threadId = isset($sub['message_thread_id']) ? absint($sub['message_thread_id']) : null;
            $targets[] = new ChatTarget($chatId, $threadId ?: null);
        }

        if ($targets === [] || trim($message) === '') {
            return;
        }

        $extra['bot_type'] = sanitize_key($botType ?: 'admin');
        $count = $this->queue->publishTelegramForTargets(
            'admin_bot_message',
            [
                'text' => $message,
                'extra' => $extra,
            ],
            $targets,
            null
        );

        Logger::log('info', 'queue', 'Telegram bot message queued', [
            'event' => 'admin_bot_message',
            'queued' => $count,
        ]);
    }
}
