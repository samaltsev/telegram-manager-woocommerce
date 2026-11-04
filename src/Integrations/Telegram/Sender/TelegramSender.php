<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Sender;

use WCTM\Integrations\Telegram\Contract\TelegramClientInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramSender
{
    public function __construct(
        private TelegramClientInterface $client
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function sendMessage(string $token, array $payload): array
    {
        return $this->client->sendMessage($token, $payload);
    }

    /**
     * Удобный метод для простых HTML-сообщений.
     *
     * @return array<string, mixed>
     */
    public function sendText(string $token, string $chatId, string $text): array
    {
        return $this->client->sendMessage($token, [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Удобный метод для plain text сообщений.
     *
     * @return array<string, mixed>
     */
    public function sendPlainText(string $token, string $chatId, string $text): array
    {
        return $this->client->sendMessage($token, [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}