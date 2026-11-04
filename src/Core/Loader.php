<?php
declare(strict_types=1);

namespace WCTM\Core;

if (!defined('ABSPATH')) {
    exit;
}

final class Loader
{
    private array $actions = [];
    private array $filters = [];

    private bool $ran = false;

    public function addAction(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'acceptedArgs');
    }

    public function addFilter(
        string $hook,
        object $component,
        string $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'acceptedArgs');
    }

    public function run(): void
    {
        if ($this->ran) {
            return;
        }

        foreach ($this->actions as $hook) {
            if (!method_exists($hook['component'], $hook['callback'])) {
                continue;
            }

            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['acceptedArgs']
            );
        }

        foreach ($this->filters as $hook) {
            if (!method_exists($hook['component'], $hook['callback'])) {
                continue;
            }

            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['acceptedArgs']
            );
        }

        $this->ran = true;
    }
}