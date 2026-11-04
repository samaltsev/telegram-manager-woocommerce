<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Error;

use WP_Error;
use Throwable;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramErrorClassifier
{
    public function classify(Throwable|WP_Error|null $error): string
    {
        if ($error === null) {
            return 'success';
        }

        // -----------------------------
        // NEW: Exception-based flow
        // -----------------------------
        if ($error instanceof Throwable && !$error instanceof WP_Error) {
            $message = strtolower($error->getMessage());

            if (str_contains($message, 'too many requests')) {
                return 'rate_limited';
            }

            if (str_contains($message, 'forbidden') || str_contains($message, 'blocked')) {
                return 'forbidden';
            }

            if (str_contains($message, 'not found') || str_contains($message, 'deactivated')) {
                return 'not_found';
            }

            return 'transient';
        }

        // -----------------------------
        // LEGACY: WP_Error
        // -----------------------------
        if (!$error instanceof WP_Error) {
            return 'permanent';
        }

        $data = $error->get_error_data();
        $body = is_array($data['body'] ?? null) ? $data['body'] : [];
        $parameters = is_array($body['parameters'] ?? null) ? $body['parameters'] : [];

        $message = strtolower((string) $error->get_error_message());
        $description = strtolower((string) ($body['description'] ?? ''));
        $httpCode = isset($data['http_code']) ? (int) $data['http_code'] : 0;

        if (!empty($parameters['retry_after']) || $httpCode === 429) {
            return 'rate_limited';
        }

        if ($httpCode === 403 || str_contains($message, 'forbidden') || str_contains($message, 'blocked')) {
            return 'forbidden';
        }

        if ($httpCode === 400 && (str_contains($message, 'chat not found') || str_contains($message, 'deactivated'))) {
            return 'not_found';
        }

        if ($httpCode >= 500 || $error->get_error_code() === 'http_request_failed') {
            return 'transient';
        }

        return 'permanent';
    }

    public function shouldRetry(Throwable|WP_Error|null $error): bool
    {
        return in_array(
            $this->classify($error),
            ['rate_limited', 'transient'],
            true
        );
    }
}