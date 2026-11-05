<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\RateLimit;

final class WordPressTransientTelegramRateLimiter implements TelegramRateLimiterInterface
{
    public function hit(string $token, string $method, int $ttlSeconds = 1): void
    {
        $key = $this->key($token, $method);
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, max(1, $ttlSeconds));
    }

    public function tooManyAttempts(string $token, string $method, int $maxAttempts, int $ttlSeconds = 1): bool
    {
        $key = $this->key($token, $method);
        $count = (int) get_transient($key);

        return $count >= $maxAttempts;
    }

    public function clear(string $token, string $method): void
    {
        delete_transient($this->key($token, $method));
    }

    private function key(string $token, string $method): string
    {
        return 'wctm_tg_rl_' . md5($token . '|' . $method);
    }
}