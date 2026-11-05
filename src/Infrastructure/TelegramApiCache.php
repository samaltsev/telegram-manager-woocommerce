<?php
declare(strict_types=1);

namespace WCTM\Infrastructure;

class TelegramApiCache
{
    private const CACHE_GROUP = 'wctm_telegram_api';
    private const BOT_INFO_TTL = HOUR_IN_SECONDS;
    private const CHAT_VALID_TTL = 15 * MINUTE_IN_SECONDS;

    /**
     * Получение информации о боте с кэшированием
     */
    public function get_bot_info(string $token): ?array
    {
        $cache_key = 'bot_info_' . md5($token);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if (false !== $cached) {
            return $cached;
        }

        $response = wp_remote_get("https://api.telegram.org/bot{$token}/getMe", [
            'timeout' => 5,
            'headers' => ['Accept' => 'application/json']
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $bot_info = $body['result'] ?? null;

        if ($bot_info) {
            wp_cache_set($cache_key, $bot_info, self::CACHE_GROUP, self::BOT_INFO_TTL);
        }

        return $bot_info;
    }

    /**
     * Кэширование успешной проверки Chat ID
     */
    public function set_chat_valid(string $token, string $chat_id): void
    {
        $cache_key = 'chat_valid_' . md5($token . '_' . $chat_id);
        wp_cache_set($cache_key, true, self::CACHE_GROUP, self::CHAT_VALID_TTL);
    }

    /**
     * Проверка, валидирован ли чат недавно
     */
    public function is_chat_valid(string $token, string $chat_id): bool
    {
        $cache_key = 'chat_valid_' . md5($token . '_' . $chat_id);
        return wp_cache_get($cache_key, self::CACHE_GROUP) === true;
    }

    /**
     * Очистка кэша при смене настроек
     */
    public function flush_token_cache(string $token): void
    {
        wp_cache_flush_group(self::CACHE_GROUP);
    }
}