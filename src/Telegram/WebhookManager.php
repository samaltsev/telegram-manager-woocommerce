<?php
declare(strict_types=1);

namespace WCTM\Telegram;

if (!defined('ABSPATH')) {
    exit;
}

class WebhookManager
{
    /**
     * Backward-compatible alias for legacy calls.
     */
    public static function webhookUrl(): string
    {
        return self::adminWebhookUrl();
    }

    public static function adminWebhookUrl(): string
    {
        return rest_url('wctm/v1/admin-webhook');
    }

    public static function isLocalEnvironment(): bool
    {
        $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (!$host) {
            return true;
        }

        $host = strtolower((string) $host);

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return true;
        }

        if (
            substr($host, -6) === '.local' ||
            substr($host, -5) === '.test' ||
            substr($host, -8) === '.invalid' ||
            substr($host, -10) === '.localhost'
        ) {
            return true;
        }

        if (
            filter_var($host, FILTER_VALIDATE_IP) &&
            preg_match('/^(10\.|127\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/', $host)
        ) {
            return true;
        }

        return false;
    }
}
