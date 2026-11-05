<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\Queue;

use RuntimeException;
use Throwable;
use WP_Error;
use WCTM\Domain\Queue\Entity\QueueJob;
use WCTM\Integrations\Telegram\Sender\TelegramMessageSender;
use WCTM\Infrastructure\Settings\SettingsRepository;
use WCTM\Telegram\ChatTarget;

if (!defined('ABSPATH')) {
    exit;
}

final class ProcessJobHandler
{
    public function __construct(
        private TelegramMessageSender $sender,
        private SettingsRepository $settings
    ) {
    }

    /**
     * Основная точка входа для QueueWorker.
     *
     * @throws RuntimeException
     */
    public function __invoke(QueueJob $job): array
    {
        return $this->handle($job);
    }

    /**
     * @throws RuntimeException
     * @return array<string, mixed>
     */
    public function handle(QueueJob $job): array
    {
        $type = method_exists($job, 'jobType')
            ? (string) $job->jobType()
            : (method_exists($job, 'type') ? (string) $job->type() : '');
        $payload = method_exists($job, 'payload') ? $job->payload() : null;

        return match ($type) {
            'send_telegram_message' => $this->handleSendTelegramMessage($payload),
            default => throw new RuntimeException(
                sprintf('Unsupported queue job type: %s', $type)
            ),
        };
    }

    public function sender(): TelegramMessageSender
    {
        return $this->sender;
    }

    /**
     * @param mixed $payload
     * @throws RuntimeException
     * @return array<string, mixed>
     */
    private function handleSendTelegramMessage($payload): array
    {
        if (!is_object($payload) && !is_array($payload)) {
            throw new RuntimeException('Queue payload must be an object or array.');
        }

        $botType = (string) $this->readPayloadValue($payload, ['botType', 'bot_type'], 'admin');
        $chatId = (string) $this->readPayloadValue($payload, ['chatId', 'chat_id'], '');
        $text = (string) $this->readPayloadValue($payload, ['text', 'message'], '');
        $extra = $this->readPayloadArray($payload, ['extra', 'options'], []);
        unset($extra['bot_type']);

        if (trim($chatId) === '') {
            throw new RuntimeException('Queue payload chat_id is empty.');
        }

        if (trim($text) === '') {
            throw new RuntimeException('Queue payload text is empty.');
        }

        $token = $this->resolveBotToken($botType);
        if ($token === '') {
            throw new RuntimeException(
                sprintf('Bot token is not configured for bot type "%s".', $botType)
            );
        }

        $target = $this->buildChatTarget($chatId, $extra);
        $result = $this->sender->send($token, $target, $text, $extra);

        if (is_wp_error($result)) {
            $classification = $this->sender->classify($result);
            $retryable = $this->sender->shouldRetry($result);

            $message = sprintf(
                'Telegram send failed [%s]: %s',
                $classification,
                $result->get_error_message()
            );

            $exception = new RuntimeException($message);

            /**
             * Передаём queue worker-у структурированный контекст через dynamic properties нельзя.
             * Поэтому упаковываем всё в message + code через отдельные методы helper ниже.
             */
            throw $this->decorateQueueException(
                $exception,
                [
                    'classification' => $classification,
                    'retryable' => $retryable,
                    'wp_error_code' => (string) $result->get_error_code(),
                    'wp_error_data' => $result->get_error_data(),
                ]
            );
        }

        return is_array($result) ? $result : [];
    }

    private function resolveBotToken(string $botType): string
    {
        $token = (string) $this->settings->getAdminBotToken();

        $token = trim($token);
        if ($token !== '') {
            return $token;
        }

        // Fallback for installs where tokens are stored in the combined plugin settings array.
        $settings = get_option('wctm_settings', []);
        if (!is_array($settings)) {
            return '';
        }

        return trim((string) ($settings['bot_token'] ?? ''));
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function buildChatTarget(string $chatId, array $extra): ChatTarget
    {
        $messageThreadId = $extra['message_thread_id'] ?? null;

        return $messageThreadId !== null && $messageThreadId !== ''
            ? new ChatTarget($chatId, $messageThreadId)
            : new ChatTarget($chatId);
    }

    /**
     * @param mixed $payload
     * @param array<int, string> $keys
     * @param mixed $default
     * @return mixed
     */
    private function readPayloadValue($payload, array $keys, $default = null)
    {
        foreach ($keys as $key) {
            if (is_array($payload) && array_key_exists($key, $payload)) {
                return $payload[$key];
            }

            if (is_object($payload)) {
                if (isset($payload->{$key})) {
                    return $payload->{$key};
                }

                if (method_exists($payload, $key)) {
                    return $payload->{$key}();
                }

                $getter = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
                if (method_exists($payload, $getter)) {
                    return $payload->{$getter}();
                }
            }
        }

        return $default;
    }

    /**
     * @param mixed $payload
     * @param array<int, string> $keys
     * @param array<string, mixed> $default
     * @return array<string, mixed>
     */
    private function readPayloadArray($payload, array $keys, array $default = []): array
    {
        $value = $this->readPayloadValue($payload, $keys, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function decorateQueueException(RuntimeException $exception, array $context): RuntimeException
    {
        $encoded = wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $message = $exception->getMessage() . ' ||queue_context||' . ($encoded ?: '{}');

        return new RuntimeException($message, 0, $exception);
    }
}
