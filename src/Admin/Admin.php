<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class Admin
{
    public function __construct(
        private SettingsOptionRepository $settings,
        private DashboardPageController $dashboardPage,
        private ConnectionPageController $connectionPage,
        private LogsPageController $logsPage,
        private SystemInfoPageController $systemInfoPage
    ) {
    }

    public function registerMenu(): void
    {
        add_menu_page(
            __('Telegram Control Center for WooCommerce', 'telegram-manager-woocommerce'),
            __('Telegram Control Center', 'telegram-manager-woocommerce'),
            'manage_woocommerce',
            'wctm-dashboard',
            [$this->dashboardPage, 'render'],
            'dashicons-format-chat',
            56
        );

        $items = [
            [__('Dashboard', 'telegram-manager-woocommerce'), 'wctm-dashboard', [$this->dashboardPage, 'render']],
            [__('Connection & Notifications', 'telegram-manager-woocommerce'), 'wctm-settings', [$this->connectionPage, 'render']],
            [__('Logs', 'telegram-manager-woocommerce'), 'wctm-logs', [$this->logsPage, 'render']],
            [__('System Info', 'telegram-manager-woocommerce'), 'wctm-system-info', [$this->systemInfoPage, 'render']],
        ];

        foreach ($items as [$label, $slug, $callback]) {
            add_submenu_page(
                'wctm-dashboard',
                $label,
                $label,
                'manage_woocommerce',
                $slug,
                $callback
            );
        }
    }

    public function registerSettings(): void
    {
        register_setting(
            'wctm_settings_group',
            SettingsOptionRepository::OPTION_KEY,
            [$this, 'sanitizeSettings']
        );
    }

    public function sanitizeSettings($input): array
    {
        return $this->settings->sanitize($input);
    }

    public function renderAdminNotices(): void
    {
    }
}
