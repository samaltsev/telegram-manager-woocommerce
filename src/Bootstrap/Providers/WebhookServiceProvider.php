<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\AdminBot\AdminBotSubscriberRepository;
use WCTM\AdminBot\AdminBotWebhookHandler;
use WCTM\AdminBot\AdminBotWebhookRoute;
use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Common\MessageSender;
use WCTM\Integrations\Telegram\Webhook\TelegramWebhookVerifier;
use WCTM\Logger\Logger;

if (!defined('ABSPATH')) {
    exit;
}

final class WebhookServiceProvider implements ServiceProviderInterface
{
    private ?Container $container = null;

    public function register(Container $container, HooksRegistrar $hooks): void
    {
        $this->container = $container;
        $container->set('logger', static fn() => new Logger());
        $container->set('telegram.webhook.verifier', static fn($c) => new TelegramWebhookVerifier($c->get('bot.options')));
        $container->set('message.sender', static fn($c) => new MessageSender($c->get('queue.publisher')));
        $container->set('admin.subscribers', static fn() => new AdminBotSubscriberRepository());
        $container->set('admin.webhook.handler', static fn($c) => new AdminBotWebhookHandler(
            $c->get('bot.options'),
            $c->get('telegram.client'),
            $c->get('logger'),
            $c->get('admin.subscribers'),
            $c->get('message.sender'),
            $c->get('feature.gate')
        ));
        $container->set('admin.webhook.route', static fn($c) => new AdminBotWebhookRoute(
            $c->get('telegram.webhook.verifier'),
            $c->get('admin.webhook.handler')
        ));
        $hooks->action('rest_api_init', $this, 'registerRestRoutes', 10, 0);
    }

    public function registerRestRoutes(): void
    {
        if ($this->container !== null) {
            $this->container->get('admin.webhook.route')->register();
        }
    }
}
