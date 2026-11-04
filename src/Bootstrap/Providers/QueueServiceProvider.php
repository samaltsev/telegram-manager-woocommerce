<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Application\Handler\Command\Queue\ProcessJobHandler;
use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Domain\Queue\Service\QueuePublisher;
use WCTM\Infrastructure\Persistence\Repository\DbQueueRepository;
use WCTM\Infrastructure\Queue\JobSerializer;
use WCTM\Infrastructure\Queue\QueueWorker;
use WCTM\Infrastructure\Settings\SettingsRepository;
use WCTM\Queue\MessageQueue;
use WCTM\Queue\QueueScheduler;
use WCTM\Queue\RetryHandler;
use WCTM\Database\LoggerRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void
    {
        // -----------------------------
        // Queue repository
        // -----------------------------
        if (!$container->has('queue.repo')) {
            $container->set('queue.repo', static function () {
                return new DbQueueRepository();
            });
        }

        // -----------------------------
        // Queue serializer
        // -----------------------------
        if (!$container->has('queue.serializer')) {
            $container->set('queue.serializer', static function () {
                return new JobSerializer();
            });
        }

        // -----------------------------
        // Queue retry policy
        // -----------------------------
        if (!$container->has('queue.retry_handler')) {
            $container->set('queue.retry_handler', static function ($container) {
                return new RetryHandler(
                    $container->get('settings.queue')
                );
            });
        }

        // -----------------------------
        // Queue publisher
        // -----------------------------
        if (!$container->has('queue.publisher')) {
            $container->set('queue.publisher', static function ($container) {
                return new QueuePublisher(
                    $container->get('queue.repo'),
                    $container->get('queue.serializer'),
                    $container->get('settings.queue')
                );
            });
        }

        // -----------------------------
        // Message queue facade
        // -----------------------------
        if (!$container->has('queue.message_queue')) {
            $container->set('queue.message_queue', static function ($container) {
                return new MessageQueue(
                    $container->get('queue.publisher')
                );
            });
        }

        // -----------------------------
        // Runtime settings adapter for queue handler
        // ВАЖНО:
        // текущий ProcessJobHandler ожидает именно
        // WCTM\Infrastructure\Settings\SettingsRepository
        // -----------------------------
        if (!$container->has('queue.settings_runtime')) {
            $container->set('queue.settings_runtime', static function () {
                return new SettingsRepository();
            });
        }

        // -----------------------------
        // Queue job handler
        // ВАЖНО:
        // telegram.sender должен быть legacy-compatible
        // TelegramMessageSender
        // -----------------------------
        if (!$container->has('queue.handler')) {
            $container->set('queue.handler', static function ($container) {
                return new ProcessJobHandler(
                    $container->get('telegram.sender'),
                    $container->get('queue.settings_runtime')
                );
            });
        }

        // -----------------------------
        // Queue worker
        // -----------------------------
        if (!$container->has('queue.worker')) {
            $container->set('queue.worker', static function ($container) {
                return new QueueWorker(
                    $container->get('queue.repo'),
                    $container->get('queue.handler'),
                    $container->get('queue.retry_handler')
                );
            });
        }

        // -----------------------------
        // Queue scheduler
        // -----------------------------
        if (!$container->has('queue_scheduler')) {
            $container->set('queue_scheduler', static function ($container) {
                return new QueueScheduler(
                    $container->get('queue.worker'),
                    $container->get('queue.repo'),
                    $container->has('logger.repo') ? $container->get('logger.repo') : new LoggerRepository()
                );
            });
        }

        // -----------------------------
        // Cron hooks
        // -----------------------------
        $hooks->action(
            'init',
            $container->get('queue_scheduler'),
            'registerCronHooks'
        );
    }
}
