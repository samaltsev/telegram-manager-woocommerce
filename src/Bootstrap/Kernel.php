<?php
declare(strict_types=1);

namespace WCTM\Bootstrap;

use WCTM\Bootstrap\Providers\AdminServiceProvider;
use WCTM\Bootstrap\Providers\NotificationServiceProvider;
use WCTM\Bootstrap\Providers\QueueServiceProvider;
use WCTM\Bootstrap\Providers\SettingsServiceProvider;
use WCTM\Bootstrap\Providers\TelegramServiceProvider;
use WCTM\Bootstrap\Providers\WebhookServiceProvider;
use WCTM\Core\Loader;

if (!defined('ABSPATH')) {
    exit;
}

final class Kernel
{
    private Container $container;

    private HooksRegistrar $hooks;

    private bool $booted = false;

    private bool $ran = false;

    public function __construct(?Container $container = null, ?HooksRegistrar $hooks = null)
    {
        $this->container = $container ?? new Container();
        $this->hooks = $hooks ?? new HooksRegistrar(new Loader());
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers() as $provider) {
            if (!$provider instanceof ServiceProviderInterface) {
                continue;
            }

            $provider->register($this->container, $this->hooks);
        }

        /**
         * Load translations at `init` to avoid early i18n notices in WP 6.7+.
         */
        $this->hooks->action(
            'init',
            $this,
            'loadTextDomain',
            0
        );

        $this->booted = true;
    }

    public function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->boot();
        $this->hooks->run();

        $this->ran = true;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function hooks(): HooksRegistrar
    {
        return $this->hooks;
    }

    /**
     * @return array<int, ServiceProviderInterface>
     */
    private function providers(): array
    {
        $providers = [
            new SettingsServiceProvider(),
            new TelegramServiceProvider(),
            new QueueServiceProvider(),
            new WebhookServiceProvider(),
            new NotificationServiceProvider(),
            new AdminServiceProvider(),
        ];

        $providers = apply_filters('wctm_kernel_providers', $providers);

        if (!is_array($providers)) {
            return [];
        }

        return array_values(
            array_filter(
                $providers,
                static fn ($provider): bool => $provider instanceof ServiceProviderInterface
            )
        );
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'telegram-manager-woocommerce',
            false,
            dirname(WCTM_BASENAME) . '/languages/'
        );
    }
}
