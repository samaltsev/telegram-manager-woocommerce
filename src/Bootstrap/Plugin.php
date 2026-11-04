<?php
declare(strict_types=1);

namespace WCTM\Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    private Kernel $kernel;

    private bool $booted = false;

    private bool $running = false;

    public function __construct(?Kernel $kernel = null)
    {
        $this->kernel = $kernel ?? new Kernel();
    }

    public function run(): void
    {
        if ($this->running) {
            return;
        }

        $this->boot();
        $this->kernel->run();
        $this->running = true;
    }

    public function kernel(): Kernel
    {
        return $this->kernel;
    }

    private function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->kernel->boot();
        $this->booted = true;
    }
}