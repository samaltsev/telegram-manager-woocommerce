<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationsPageController
{
    public function __construct(private SettingsOptionRepository $settings)
    {
    }

    public function render(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=wctm-settings'));
        exit;
    }

    private function guard(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }
    }
}
