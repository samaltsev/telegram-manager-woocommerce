<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Integrations\Telegram\Client\TelegramClient;
use WCTM\Integrations\Telegram\Debug\TelegramConnectionVerifyer;
use WCTM\Integrations\Telegram\Error\TelegramErrorClassifier;
use WCTM\Integrations\Telegram\RateLimit\WordPressTransientTelegramRateLimiter;
use WCTM\Integrations\Telegram\Sender\TelegramMessageSender;
use WCTM\Integrations\Telegram\Sender\TelegramSender;
use WCTM\Integrations\Telegram\Support\TelegramPayloadFactory;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void
    {
        if (!$container->has('telegram.rate_limiter')) {
            $container->set('telegram.rate_limiter', static function () {
                return new WordPressTransientTelegramRateLimiter();
            });
        }

        if (!$container->has('telegram.client')) {
            $container->set('telegram.client', static function ($container) {
                return new TelegramClient(
                    $container->get('telegram.rate_limiter'),
                    20,
                    2,
                    25
                );
            });
        }

        if (!$container->has('telegram.payload_factory')) {
            $container->set('telegram.payload_factory', static function ($container) {
                return new TelegramPayloadFactory(
                    $container->get('bot.options')
                );
            });
        }

        if (!$container->has('telegram.error_classifier')) {
            $container->set('telegram.error_classifier', static function () {
                return new TelegramErrorClassifier();
            });
        }

        if (!$container->has('telegram.sender')) {
            $container->set('telegram.sender', static function ($container) {
                return new TelegramMessageSender(
                    $container->get('telegram.client'),
                    $container->get('telegram.payload_factory'),
                    $container->get('telegram.error_classifier')
                );
            });
        }

        if (!$container->has('telegram.simple_sender')) {
            $container->set('telegram.simple_sender', static function ($container) {
                return new TelegramSender(
                    $container->get('telegram.client')
                );
            });
        }

        if (!$container->has('telegram.connection_tester')) {
            $container->set('telegram.connection_tester', static function ($container) {
                return new TelegramConnectionVerifyer(
                    $container->get('telegram.client'),
                    $container->get('bot.options')
                );
            });
        }
    }
}