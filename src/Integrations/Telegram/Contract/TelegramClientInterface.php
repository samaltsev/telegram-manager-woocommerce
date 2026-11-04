<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Contract;

use WCTM\Integrations\Telegram\DTO\BotCommand;

interface TelegramClientInterface
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function call(string $token, string $method, array $payload = []): array;

    /**
     * @return array<string,mixed>
     */
    public function getMe(string $token): array;

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function getUpdates(string $token, array $args = []): array;

    /**
     * @return array<string,mixed>
     */
    public function setWebhook(string $token, string $url, string $secret = ''): array;

    /**
     * @return array<string,mixed>
     */
    public function deleteWebhook(string $token): array;

    /**
     * @return array<string,mixed>
     */
    public function getWebhookInfo(string $token): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendMessage(string $token, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function answerCallbackQuery(string $token, array $payload): array;

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function editMessageReplyMarkup(string $token, array $payload): array;

    /**
     * @return array<string,mixed>
     */
    public function setMyDescription(string $token, string $description): array;

    /**
     * @return array<string,mixed>
     */
    public function setMyShortDescription(string $token, string $shortDescription): array;

    /**
     * @param list<BotCommand> $commands
     * @return array<string,mixed>
     */
    public function setMyCommands(string $token, array $commands): array;
}