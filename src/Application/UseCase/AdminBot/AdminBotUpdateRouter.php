<?php
declare(strict_types=1);

namespace WCTM\Application\UseCase\AdminBot;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminBotUpdateRouter
{
    /**
     * @return array<string, mixed>
     */
    public function route(array $update): array
    {
        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            return ['type' => 'callback', 'callback_query' => $update['callback_query']];
        }

        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            return ['type' => 'ignore'];
        }

        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return ['type' => 'ignore'];
        }

        $username = (string) ($message['from']['username'] ?? '');
        $text = trim((string) ($message['text'] ?? ''));

        return [
            'type' => 'message',
            'chat_id' => $chatId,
            'username' => $username,
            'text' => $text,
        ];
    }
}
