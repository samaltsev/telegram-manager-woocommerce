<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Backward-compatible wrapper for old code paths.
 * The visible admin page is now ConnectionPageController.
 */
final class SettingsPageController
{
    public function __construct(private SettingsOptionRepository $settings)
    {
    }

    public function render(): void
    {
        (new ConnectionPageController($this->settings))->render();
    }
}
