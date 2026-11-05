<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Support;

use WCTM\Integrations\WordPress\Option\BotOptionRepository;
use WCTM\Telegram\ChatTarget;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramPayloadFactory
{
    public function __construct(
        private BotOptionRepository $botOptions
    ) {
    }

    /**
     * Собирает payload для Telegram sendMessage.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function buildSendMessagePayload(
        ChatTarget $target,
        string $text,
        array $extra = []
    ): array {
        $chatId = $this->extractChatId($target);

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $this->normalizeParseMode($this->botOptions->getParseMode()),
            'disable_web_page_preview' => $this->botOptions->disableWebPreview() ? 'true' : 'false',
        ];

        $threadId = $this->extractThreadId($target);
        if ($threadId !== null) {
            $payload['message_thread_id'] = $threadId;
        }

        /**
         * extra может дополнять payload, но не должен ломать обязательные поля
         * случайными некорректными значениями.
         */
        $extra = $this->sanitizeExtra($extra);

        $payload = array_merge($payload, $extra);

        // На всякий случай восстанавливаем критичные поля.
        $payload['chat_id'] = $chatId;
        $payload['text'] = $text;

        if (!isset($payload['parse_mode']) || !is_string($payload['parse_mode']) || $payload['parse_mode'] === '') {
            $payload['parse_mode'] = $this->normalizeParseMode($this->botOptions->getParseMode());
        }

        if (!isset($payload['disable_web_page_preview'])) {
            $payload['disable_web_page_preview'] = $this->botOptions->disableWebPreview() ? 'true' : 'false';
        }

        if ($threadId !== null) {
            $payload['message_thread_id'] = $threadId;
        }

        return $payload;
    }

    private function extractChatId(ChatTarget $target): string
    {
        if (property_exists($target, 'chatId')) {
            return (string) $target->chatId;
        }

        if (method_exists($target, 'chatId')) {
            return (string) $target->chatId();
        }

        if (method_exists($target, 'getChatId')) {
            return (string) $target->getChatId();
        }

        return '';
    }

    private function extractThreadId(ChatTarget $target): ?int
    {
        if (property_exists($target, 'threadId')) {
            $value = $target->threadId;
            return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        }

        if (method_exists($target, 'threadId')) {
            $value = $target->threadId();
            return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        }

        if (method_exists($target, 'getThreadId')) {
            $value = $target->getThreadId();
            return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function sanitizeExtra(array $extra): array
    {
        $sanitized = [];

        foreach ($extra as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            /**
             * chat_id / text управляются фабрикой.
             * Их нельзя позволять перезаписывать через extra.
             */
            if (in_array($key, ['chat_id', 'text'], true)) {
                continue;
            }

            if ($key === 'parse_mode') {
                if (is_string($value) && $value !== '') {
                    $sanitized[$key] = $this->normalizeParseMode($value);
                }
                continue;
            }

            if ($key === 'disable_web_page_preview') {
                $sanitized[$key] = $this->normalizeBooleanLike($value);
                continue;
            }

            if ($key === 'message_thread_id') {
                if (is_numeric($value) && (int) $value > 0) {
                    $sanitized[$key] = (int) $value;
                }
                continue;
            }

            if ($key === 'reply_markup' && is_array($value)) {
                $sanitized[$key] = $value;
                continue;
            }

            if (is_scalar($value) || $value === null || is_array($value)) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function normalizeParseMode(string $parseMode): string
    {
        $parseMode = trim($parseMode);

        return match (strtoupper($parseMode)) {
            'HTML' => 'HTML',
            'MARKDOWNV2' => 'MarkdownV2',
            'MARKDOWN' => 'Markdown',
            default => 'HTML',
        };
    }

    /**
     * Telegram принимает bool/string-подобные значения.
     *
     * @param mixed $value
     * @return string|bool
     */
    private function normalizeBooleanLike(mixed $value): string|bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if (in_array($normalized, ['true', '1', 'yes'], true)) {
                return 'true';
            }

            if (in_array($normalized, ['false', '0', 'no'], true)) {
                return 'false';
            }
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 'true' : 'false';
        }

        return 'false';
    }
}