<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Client;

use WCTM\Integrations\Telegram\Contract\TelegramClientInterface;
use WCTM\Integrations\Telegram\DTO\BotCommand;
use WCTM\Integrations\Telegram\Exception\TelegramApiException;
use WCTM\Integrations\Telegram\Exception\TelegramRateLimitException;
use WCTM\Integrations\Telegram\Exception\TelegramTransportException;
use WCTM\Integrations\Telegram\RateLimit\TelegramRateLimiterInterface;
use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramClient implements TelegramClientInterface
{
    public function __construct(
        private TelegramRateLimiterInterface $rateLimiter,
        private int $timeoutSeconds = 20,
        private int $maxRetries = 2,
        private int $maxAttemptsPerSecond = 25
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function call(string $token, string $method, array $payload = []): array
    {
        $this->assertToken($token);

        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            $attempt++;

            try {
                return $this->doCall($token, $method, $payload, $attempt);
            } catch (TelegramRateLimitException $e) {
                $lastException = $e;

                Logger::log(
                    'error',
                    'telegram_send',
                    'Telegram rate limit exception',
                    [
                        'event' => $method,
                        'attempt' => $attempt,
                        'retry_after' => $e->retryAfterSeconds(),
                        'message' => $e->getMessage(),
                    ]
                );

                if ($attempt > $this->maxRetries) {
                    throw $e;
                }

                sleep(max(1, $e->retryAfterSeconds()));
            } catch (TelegramTransportException|TelegramApiException $e) {
                $lastException = $e;

                Logger::log(
                    'error',
                    'telegram_send',
                    'Telegram request failed',
                    [
                        'event' => $method,
                        'attempt' => $attempt,
                        'message' => $e->getMessage(),
                        'exception_class' => $e::class,
                    ]
                );

                if ($attempt > $this->maxRetries || !$this->isRetryable($e)) {
                    throw $e;
                }

                usleep($this->backoffMicroseconds($attempt));
            }
        }

        throw $lastException instanceof \Throwable
            ? $lastException
            : new TelegramTransportException('Unknown Telegram client failure.');
    }

    /**
     * @return array<string,mixed>
     */
    public function getMe(string $token): array
    {
        return $this->call($token, 'getMe');
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    public function getUpdates(string $token, array $args = []): array
    {
        return $this->call(
            $token,
            'getUpdates',
            wp_parse_args($args, ['offset' => 0, 'limit' => 20, 'timeout' => 0])
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function setWebhook(string $token, string $url, string $secret = ''): array
    {
        return $this->call($token, 'setWebhook', [
            'url' => esc_url_raw($url),
            'secret_token' => $secret,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function deleteWebhook(string $token): array
    {
        return $this->call($token, 'deleteWebhook');
    }

    /**
     * @return array<string,mixed>
     */
    public function getWebhookInfo(string $token): array
    {
        return $this->call($token, 'getWebhookInfo');
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function sendMessage(string $token, array $payload): array
    {
        return $this->call($token, 'sendMessage', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function answerCallbackQuery(string $token, array $payload): array
    {
        return $this->call($token, 'answerCallbackQuery', $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function editMessageReplyMarkup(string $token, array $payload): array
    {
        return $this->call($token, 'editMessageReplyMarkup', $payload);
    }

    /**
     * @return array<string,mixed>
     */
    public function setMyDescription(string $token, string $description): array
    {
        return $this->call($token, 'setMyDescription', [
            'description' => $description,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function setMyShortDescription(string $token, string $shortDescription): array
    {
        return $this->call($token, 'setMyShortDescription', [
            'short_description' => $shortDescription,
        ]);
    }

    /**
     * @param list<BotCommand> $commands
     * @return array<string,mixed>
     */
    public function setMyCommands(string $token, array $commands): array
    {
        $normalized = array_map(
            static fn (BotCommand $command): array => $command->toArray(),
            $commands
        );

        return $this->call($token, 'setMyCommands', [
            'commands' => $normalized,
        ]);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function doCall(string $token, string $method, array $payload, int $attempt): array
    {
        if ($this->rateLimiter->tooManyAttempts($token, $method, $this->maxAttemptsPerSecond, 1)) {
            throw new TelegramRateLimitException(
                'Local Telegram rate limit exceeded.',
                429,
                null,
                1
            );
        }

        $this->rateLimiter->hit($token, $method, 1);

        $payload = $this->normalizePayload($payload);

        $response = wp_remote_post(
            $this->endpoint($token, $method),
            [
                'timeout' => $this->timeoutSeconds,
                'body' => $payload,
            ]
        );

        if (is_wp_error($response)) {
            throw new TelegramTransportException($response->get_error_message());
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        Logger::log(
            $code === 200 && is_array($decoded) && !empty($decoded['ok']) ? 'info' : 'error',
            'telegram_send',
            'Telegram API response for ' . $method,
            [
                'event' => $method,
                'attempt' => $attempt,
                'request_payload' => $payload,
                'response_payload' => is_array($decoded) ? $decoded : $body,
                'chat_id' => $payload['chat_id'] ?? '',
            ]
        );

        if (!is_array($decoded)) {
            throw new TelegramApiException(
                'Telegram returned invalid JSON.',
                $code,
                null
            );
        }

        if ($code === 429 || (!empty($decoded['error_code']) && (int) $decoded['error_code'] === 429)) {
            $retryAfter = 1;

            if (isset($decoded['parameters']['retry_after'])) {
                $retryAfter = max(1, (int) $decoded['parameters']['retry_after']);
            }

            throw new TelegramRateLimitException(
                (string) ($decoded['description'] ?? 'Telegram rate limit exceeded.'),
                $code ?: 429,
                $decoded,
                $retryAfter
            );
        }

        if ($code !== 200 || empty($decoded['ok'])) {
            throw new TelegramApiException(
                (string) ($decoded['description'] ?? 'Telegram API error.'),
                $code,
                $decoded
            );
        }

        return $decoded;
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private function normalizePayload(array $payload): array
    {
        if (isset($payload['reply_markup']) && is_array($payload['reply_markup'])) {
            $payload['reply_markup'] = wp_json_encode(
                $payload['reply_markup'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        if (isset($payload['commands']) && is_array($payload['commands'])) {
            $payload['commands'] = wp_json_encode(
                $payload['commands'],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }

        return $payload;
    }

    private function assertToken(string $token): void
    {
        if ($token === '') {
            throw new TelegramTransportException('Telegram bot token is empty.');
        }

        if (!preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', $token)) {
            throw new TelegramTransportException('Invalid Telegram bot token format.');
        }
    }

    private function endpoint(string $token, string $method): string
    {
        return 'https://api.telegram.org/bot' . $token . '/' . $method;
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e instanceof TelegramTransportException) {
            return true;
        }

        if ($e instanceof TelegramApiException) {
            return in_array($e->httpCode(), [408, 425, 429, 500, 502, 503, 504], true);
        }

        return false;
    }

    private function backoffMicroseconds(int $attempt): int
    {
        return match ($attempt) {
            1 => 250000,
            2 => 500000,
            default => 1000000,
        };
    }
}