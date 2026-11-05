<?php
declare(strict_types=1);

namespace WCTM\Logger;

use WCTM\Database\LoggerRepository;

if (!interface_exists('Psr\\Log\\LoggerInterface')) {
    require_once __DIR__ . '/LoggerInterfaceStub.php';
}

class LoggerService implements \Psr\Log\LoggerInterface
{
    private LoggerRepository $repository;

    /** @var array<int, string> */
    private array $sensitiveKeys = [
        'token',
        'bot_token',
        'authorization',
        'x-telegram-bot-api-secret-token',
        'secret',
        'password',
        'pass',
        'api_key',
        'access_token',
        'refresh_token',
        'webhook_secret',
        'bind_url',
        'telegram_user_id',
    ];

    public function __construct(LoggerRepository $repository)
    {
        $this->repository = $repository;
    }

    public function log($level, $message, array $context = [])
    {
        $sanitizedContext = $this->sanitizeContext($context);

        if (isset($sanitizedContext['chat_id']) && is_string($sanitizedContext['chat_id'])) {
            $sanitizedContext['chat_hash'] = hash('sha256', $sanitizedContext['chat_id']);
            unset($sanitizedContext['chat_id']);
        }

        $this->repository->insert([
            'level' => sanitize_key((string) $level),
            'context' => sanitize_key((string) ($sanitizedContext['context'] ?? 'general')),
            'event' => sanitize_key((string) ($sanitizedContext['event'] ?? 'log_event')),
            'order_id' => isset($sanitizedContext['order_id']) ? (int) $sanitizedContext['order_id'] : null,
            'request_payload' => $sanitizedContext,
            'message' => (string) $message,
        ]);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function sanitizeContext(array $context): array
    {
        $sanitized = [];
        foreach ($context as $key => $value) {
            $sanitized[$key] = $this->sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeValue(string $key, $value)
    {
        if ($this->isSensitiveKey($key)) {
            if (is_string($value)) {
                return $this->maskSecret($value);
            }

            return '[redacted]';
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $childKey => $childValue) {
                $out[$childKey] = $this->sanitizeValue((string) $childKey, $childValue);
            }

            return $out;
        }

        if (is_string($value)) {
            return $this->maskPossibleTokenInString($value);
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower(trim($key));
        foreach ($this->sensitiveKeys as $needle) {
            if (strpos($normalizedKey, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function maskSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $length = strlen($value);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 4) . str_repeat('*', max(3, $length - 8)) . substr($value, -4);
    }

    private function maskPossibleTokenInString(string $value): string
    {
        return (string) preg_replace('/\\b\\d{6,}:[A-Za-z0-9_-]{20,}\\b/', '[redacted-token]', $value);
    }

    public function debug($message, array $context = []) { $this->log('info', $message, $context); }
    public function info($message, array $context = []) { $this->log('info', $message, $context); }
    public function error($message, array $context = []) { $this->log('error', $message, $context); }
    public function emergency($message, array $context = []) { $this->log('error', $message, $context); }
    public function alert($message, array $context = []) { $this->log('warning', $message, $context); }
    public function critical($message, array $context = []) { $this->log('error', $message, $context); }
    public function warning($message, array $context = []) { $this->log('warning', $message, $context); }
    public function notice($message, array $context = []) { $this->log('info', $message, $context); }
}
