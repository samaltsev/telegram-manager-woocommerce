<?php
declare(strict_types=1);

namespace WCTM\Bootstrap;

use WCTM\Core\Loader;

if (!defined('ABSPATH')) {
    exit;
}

final class HooksRegistrar
{
    private Loader $loader;

    public function __construct(Loader $loader)
    {
        $this->loader = $loader;
    }

    public function action(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->loader->addAction($hook, $component, $callback, $priority, $acceptedArgs);
    }

    public function filter(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->loader->addFilter($hook, $component, $callback, $priority, $acceptedArgs);
    }

    public function run(): void
    {
        $this->loader->run();
    }
}