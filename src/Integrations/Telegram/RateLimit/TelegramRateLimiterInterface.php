<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\RateLimit;

interface TelegramRateLimiterInterface
{
    public function hit(string $token, string $method, int $ttlSeconds = 1): void;

    public function tooManyAttempts(string $token, string $method, int $maxAttempts, int $ttlSeconds = 1): bool;

    public function clear(string $token, string $method): void;
}