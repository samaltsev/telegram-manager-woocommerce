<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Sender;

use WCTM\Integrations\Telegram\Contract\TelegramClientInterface;
use WCTM\Integrations\Telegram\Error\TelegramErrorClassifier;
use WCTM\Integrations\Telegram\Exception\TelegramApiException;
use WCTM\Integrations\Telegram\Exception\TelegramRateLimitException;
use WCTM\Integrations\Telegram\Exception\TelegramTransportException;
use WCTM\Integrations\Telegram\Support\TelegramPayloadFactory;
use WCTM\Logger\Logger;
use WCTM\Telegram\ChatTarget;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramMessageSender
{
    public function __construct(
        private TelegramClientInterface $client,
        private TelegramPayloadFactory $payloadFactory,
        private TelegramErrorClassifier $errorClassifier
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>|WP_Error
     */
    public function send(string $token, ChatTarget $target, string $text, array $extra = [])
    {
        $chatId = $this->extractChatId($target);

        if ($chatId === '') {
            return new WP_Error('invalid_chat_target', 'Telegram chat target is empty.');
        }

        $body = $this->payloadFactory->buildSendMessagePayload($target, $text, $extra);

        return $this->postToTelegram($token, 'sendMessage', $body, $chatId);
    }

    /**
     * Compatibility wrapper for legacy callers.
     *
     * @return array<string, mixed>|WP_Error
     */
    public function sendMessage(string $token, string $chatId, string $message)
    {
        return $this->send($token, new ChatTarget($chatId), $message, []);
    }

    public function shouldRetry(WP_Error $error): bool
    {
        if (method_exists($this->errorClassifier, 'shouldRetry')) {
            return (bool) $this->errorClassifier->shouldRetry($error);
        }

        $classification = $this->classify($error);

        return in_array($classification, ['retryable', 'rate_limited'], true);
    }

    public function classify(WP_Error $error): string
    {
        if (method_exists($this->errorClassifier, 'classify')) {
            $classified = $this->errorClassifier->classify($error);

            if (is_string($classified) && $classified !== '') {
                return $classified;
            }
        }

        $data = $error->get_error_data();
        $message = mb_strtolower((string) $error->get_error_message());
        $code = (string) $error->get_error_code();

        $httpCode = 0;
        if (is_array($data) && isset($data['http_code'])) {
            $httpCode = (int) $data['http_code'];
        }

        if (
            $httpCode === 429 ||
            strpos($message, 'too many requests') !== false ||
            strpos($message, 'retry after') !== false
        ) {
            return 'rate_limited';
        }

        if (
            $httpCode === 403 ||
            strpos($message, 'forbidden') !== false ||
            strpos($message, 'bot was blocked') !== false ||
            strpos($message, 'bot blocked') !== false ||
            strpos($message, 'user is deactivated') !== false
        ) {
            return 'forbidden';
        }

        if (
            $httpCode >= 500 ||
            $code === 'http_request_failed' ||
            strpos($message, 'timeout') !== false ||
            strpos($message, 'timed out') !== false ||
            strpos($message, 'connection') !== false
        ) {
            return 'retryable';
        }

        return 'permanent';
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    protected function postToTelegram(string $token, string $method, array $body, string $chatId)
    {
        $this->logRequest($chatId, $method, $body);

        try {
            if ($method === 'sendMessage') {
                $response = $this->client->sendMessage($token, $body);
            } else {
                $response = $this->client->call($token, $method, $body);
            }

            $rawBody = wp_json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $httpCode = 200;

            $this->logResponse(
                $chatId,
                $method,
                (string) $rawBody,
                $httpCode,
                '',
                'info'
            );

            return $response;
        } catch (TelegramRateLimitException $e) {
            $error = new WP_Error(
                'telegram_rate_limited',
                'Telegram API error: ' . $e->getMessage(),
                [
                    'http_code' => 429,
                    'telegram_method' => $method,
                    'chat_id' => $chatId,
                    'retry_after' => $e->retryAfterSeconds(),
                    'response_body' => $e->responseBody(),
                ]
            );

            $this->logResponse(
                $chatId,
                $method,
                '',
                429,
                $error->get_error_message(),
                'error'
            );

            return $error;
        } catch (TelegramApiException $e) {
            $httpCode = $e->httpCode();

            $error = new WP_Error(
                'wctm_telegram_error',
                'Telegram API error: ' . $e->getMessage(),
                [
                    'http_code' => $httpCode,
                    'telegram_method' => $method,
                    'chat_id' => $chatId,
                    'response_body' => $e->responseBody(),
                ]
            );

            $this->logResponse(
                $chatId,
                $method,
                '',
                $httpCode,
                $error->get_error_message(),
                'error'
            );

            return $error;
        } catch (TelegramTransportException $e) {
            $error = new WP_Error(
                'http_request_failed',
                'Telegram API error: ' . $e->getMessage(),
                [
                    'http_code' => 0,
                    'telegram_method' => $method,
                    'chat_id' => $chatId,
                ]
            );

            $this->logResponse(
                $chatId,
                $method,
                '',
                0,
                $error->get_error_message(),
                'error'
            );

            return $error;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function logRequest(string $chatId, string $method, array $payload): void
    {
        global $wpdb;

        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix)) {
            Logger::log('info', 'telegram_send', 'Sending message', [
                'chat_id' => $chatId,
                'method' => $method,
                'request_payload' => $payload,
            ]);
            return;
        }

        $logsTable = $wpdb->prefix . 'wctm_logs';
        $now = current_time('mysql');

        $wpdb->insert($logsTable, [
            'level' => 'info',
            'context' => 'telegram_send',
            'message' => 'Sending message',
            'chat_id' => $chatId,
            'request_payload' => maybe_serialize([
                'method' => $method,
                'body' => $payload,
            ]),
            'created_at' => $now,
        ]);
    }

    protected function logResponse(
        string $chatId,
        string $method,
        string $rawBody,
        int $httpCode,
        string $message = '',
        string $level = 'info'
    ): void {
        global $wpdb;

        $logPayload = [
            'method' => $method,
            'http_code' => $httpCode,
            'body' => $rawBody,
        ];

        if (!isset($wpdb) || !is_object($wpdb) || !isset($wpdb->prefix)) {
            Logger::log($level, 'telegram_send', $message !== '' ? $message : 'Telegram API response', [
                'chat_id' => $chatId,
                'response_payload' => $logPayload,
            ]);
            return;
        }

        $logsTable = $wpdb->prefix . 'wctm_logs';
        $now = current_time('mysql');

        $wpdb->insert($logsTable, [
            'level' => $level,
            'context' => 'telegram_send',
            'message' => $message !== '' ? $message : 'Telegram API response',
            'chat_id' => $chatId,
            'response_payload' => maybe_serialize($logPayload),
            'created_at' => $now,
        ]);
    }

    protected function extractChatId(ChatTarget $target): string
    {
        if (method_exists($target, 'chatId')) {
            return (string) $target->chatId();
        }

        if (method_exists($target, 'getChatId')) {
            return (string) $target->getChatId();
        }

        if (property_exists($target, 'chatId')) {
            return (string) $target->chatId;
        }

        return '';
    }
}
