<?php
declare(strict_types=1);

namespace WCTM\Queue;

use Throwable;
use WP_Error;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retry policy with exponential backoff + jitter.
 */
final class RetryHandler
{
    public function __construct(
        private QueueSettingsRepository $queueSettings
    ) {
    }

    /**
     * @param mixed $error
     */
    public function nextDelay($error, int $attempt = 1): int
    {
        $baseDelay = max(5, absint($this->queueSettings->get('retry_delay', 300)));
        $maxDelay = max($baseDelay, absint($this->queueSettings->get('retry_max_delay', 3600)));

        $retryAfter = $this->extractRetryAfter($error);
        if ($retryAfter !== null && $retryAfter > 0) {
            return min($maxDelay, $retryAfter);
        }

        $attempt = max(1, $attempt);

        $delay = (int) min(
            $maxDelay,
            $baseDelay * (2 ** min($attempt - 1, 10))
        );

        return $this->withJitter($delay);
    }

    /**
     * @param mixed $error
     */
    public function shouldRetry($error, int $attempt, int $maxAttempts): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        if ($error instanceof WP_Error) {
            $data = $error->get_error_data();
            $httpCode = is_array($data) && isset($data['http_code']) ? (int) $data['http_code'] : 0;
            $message = strtolower($error->get_error_message());

            if ($httpCode === 429 || str_contains($message, 'too many requests')) {
                return true;
            }

            if ($httpCode >= 500 || $error->get_error_code() === 'http_request_failed') {
                return true;
            }

            if (
                str_contains($message, 'timeout') ||
                str_contains($message, 'timed out') ||
                str_contains($message, 'connection')
            ) {
                return true;
            }

            return false;
        }

        if ($error instanceof Throwable) {
            $message = strtolower($error->getMessage());

            return
                str_contains($message, 'too many requests') ||
                str_contains($message, 'rate limit') ||
                str_contains($message, 'timeout') ||
                str_contains($message, 'timed out') ||
                str_contains($message, 'connection') ||
                str_contains($message, 'temporarily') ||
                str_contains($message, 'transient');
        }

        return false;
    }

    /**
     * @param mixed $error
     */
    private function extractRetryAfter($error): ?int
    {
        if ($error instanceof WP_Error) {
            $data = $error->get_error_data();

            if (is_array($data)) {
                if (isset($data['retry_after']) && is_numeric($data['retry_after'])) {
                    return max(1, (int) $data['retry_after']);
                }

                if (
                    isset($data['body']['parameters']['retry_after']) &&
                    is_numeric($data['body']['parameters']['retry_after'])
                ) {
                    return max(1, (int) $data['body']['parameters']['retry_after']);
                }
            }
        }

        if ($error instanceof Throwable) {
            if (preg_match('/retry\s+after\s+(\d+)/i', $error->getMessage(), $matches) === 1) {
                return max(1, (int) $matches[1]);
            }
        }

        return null;
    }

    private function withJitter(int $delay): int
    {
        $jitterPercent = 0.15;
        $spread = max(1, (int) round($delay * $jitterPercent));

        try {
            return max(1, $delay + random_int(-$spread, $spread));
        } catch (Throwable) {
            return $delay;
        }
    }
}