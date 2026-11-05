<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\FeatureFlagsRepository;
use WCTM\Integrations\WordPress\Option\NotificationSettingsRepository;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;
use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Licensing\FeatureGate;
use WCTM\Licensing\LicenseManager;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void
    {
        $container->set('settings.bots', static fn() => new BotSettingsRepository());
        $container->set('settings.notifications', static fn() => new NotificationSettingsRepository());
        $container->set('settings.queue', static fn() => new QueueSettingsRepository());
        $container->set('settings.templates', static fn() => new TemplateSettingsRepository());
        $container->set('settings.features', static fn() => new FeatureFlagsRepository());
        $container->set('settings', static fn($c) => new SettingsOptionRepository(
            $c->get('settings.bots'),
            $c->get('settings.notifications'),
            $c->get('settings.queue'),
            $c->get('settings.templates'),
            $c->get('settings.features')
        ));
        $container->set('bot.options', static fn($c) => new BotOptionRepository(
            $c->get('settings.bots'),
            $c->get('settings.templates')
        ));
        $container->set('license.manager', static fn() => new LicenseManager());
        $container->set('feature.gate', static fn($c) => new FeatureGate(
            $c->get('license.manager'),
            $c->get('settings.features')
        ));
    }
}
