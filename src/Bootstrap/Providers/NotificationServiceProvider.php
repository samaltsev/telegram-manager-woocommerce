<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Notifications\OrderNotifierService;
use WCTM\Notifications\UnifiedNotifier;
use WCTM\Templates\TemplateParser;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void
    {
        if (!$container->has('template.parser')) {
            $container->set('template.parser', static fn($c) => new TemplateParser(
                $c->get('settings.templates')->getParseMode()
            ));
        }
        $container->set(UnifiedNotifier::class, static fn($c) => new UnifiedNotifier(
            $c->get('settings.bots'),
            $c->get('telegram.sender'),
            $c->get('queue.publisher'),
            $c->get('settings.templates'),
            $c->get('template.parser')
        ));
        $container->set(OrderNotifierService::class, static fn($c) => new OrderNotifierService(
            $c->get(UnifiedNotifier::class)
        ));

        $service = $container->get(OrderNotifierService::class);
        $hooks->action('woocommerce_new_order', $service, 'notifyNewOrder');
        $hooks->action('wctm_delayed_new_order_notification', $service, 'notifyNewOrder');
        $hooks->action('woocommerce_order_status_changed', $service, 'notifyOrderStatusChange', 10, 3);
        $hooks->action('woocommerce_order_refunded', $service, 'notifyRefundCreated', 10, 2);
        // Free version intentionally does not send separate payment/processing notifications.
    }
}
