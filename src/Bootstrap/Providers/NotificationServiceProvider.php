<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Notifications\OrderNotifierService;
use WCTM\Notifications\ProductStatusNotifier;
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
        $container->set(ProductStatusNotifier::class, static fn($c) => new ProductStatusNotifier(
            $c->get(UnifiedNotifier::class)
        ));
        $products = $container->get(ProductStatusNotifier::class);
        $hooks->action('woocommerce_product_set_stock_status', $products, 'stockStatusChanged', 10, 3);
        $hooks->action('woocommerce_variation_set_stock_status', $products, 'stockStatusChanged', 10, 3);
        $hooks->action('woocommerce_low_stock', $products, 'lowStock');
        $hooks->action('woocommerce_no_stock', $products, 'noStock');
        $hooks->action('woocommerce_product_on_backorder', $products, 'backorder');
        $hooks->action('woocommerce_reduce_order_stock', $products, 'orderStockChanged');
        $hooks->action('woocommerce_restore_order_stock', $products, 'orderStockChanged');
        $hooks->action('woocommerce_product_set_stock', $products, 'quantityChanged');
        $hooks->action('woocommerce_variation_set_stock', $products, 'quantityChanged');
        $hooks->action('transition_post_status', $products, 'publicationChanged', 10, 3);
        // Payment notification is independent; UnifiedNotifier suppresses the duplicate
        // when payment completion already produced a processing-status notification.
        $hooks->action('woocommerce_payment_complete', $service, 'notifyPaymentReceived');
    }
}
