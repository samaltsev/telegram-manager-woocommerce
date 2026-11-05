<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Exception;

final class TelegramApiException extends TelegramException
{
    /**
     * @param array<string,mixed>|null $responseBody
     */
    public function __construct(
        string $message,
        private int $httpCode = 0,
        private ?array $responseBody = null
    ) {
        parent::__construct($message, $httpCode);
    }

    public function httpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function responseBody(): ?array
    {
        return $this->responseBody;
    }
}