<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Exception;

final class TelegramRateLimitException extends TelegramApiException
{
    public function __construct(
        string $message,
        int $httpCode = 429,
        ?array $responseBody = null,
        private int $retryAfterSeconds = 1
    ) {
        parent::__construct($message, $httpCode, $responseBody);
    }

    public function retryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}