<?php
declare(strict_types=1);

namespace WCTM\Logger;

class Logger
{
    protected static ?LoggerService $service = null;

    public static function setService(LoggerService $service): void
    {
        self::$service = $service;
    }

    public static function log($level, $context, $message = '', array $meta = []): void
    {
        if (!self::$service) {
            return;
        }

        $payload = is_array($context) ? $context : ['context' => (string) $context];
        self::$service->log((string) $level, $message !== '' ? $message : (string) $context, array_merge($payload, $meta));
    }

    public static function info(string $event, array $meta = []): void
    {
        self::log('info', ['event' => sanitize_key($event)], $event, $meta);
    }

    public static function warning(string $event, array $meta = []): void
    {
        self::log('warning', ['event' => sanitize_key($event)], $event, $meta);
    }

    public static function error(string $event, array $meta = []): void
    {
        self::log('error', ['event' => sanitize_key($event)], $event, $meta);
    }

    public static function debugStep(string $step, array $data = []): void
    {
        if (!self::$service) {
            return;
        }

        self::$service->debug('STEP: ' . $step, $data);
    }
}
