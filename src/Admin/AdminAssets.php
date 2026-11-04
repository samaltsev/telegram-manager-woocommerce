<?php
declare(strict_types=1);

namespace WCTM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminAssets
{
    public function __construct(
        private string $pluginUrl,
        private string $version
    ) {
        $this->pluginUrl = trailingslashit($pluginUrl);
    }

    public function enqueue(string $hookSuffix): void
    {
        if (strpos($hookSuffix, 'wctm-') === false && $hookSuffix !== 'toplevel_page_wctm-dashboard') {
            return;
        }

        $cssFiles = [
            'variables',
            'layout',
            'sidebar',
            'cards',
            'forms',
            'tables',
            'telegram-preview',
            'dashboard',
            'connection',
            'logs',
            'system',
            'animations',
            'responsive',
            'components',
        ];

        foreach ($cssFiles as $index => $file) {
            wp_enqueue_style(
                'wctm-admin-' . $file,
                $this->pluginUrl . 'assets/admin/css/' . $file . '.css',
                $index === 0 ? [] : ['wctm-admin-' . $cssFiles[$index - 1]],
                $this->version
            );
        }

        $jsFiles = [
            'utils' => ['wp-i18n'],
            'toast' => ['wctm-admin-utils'],
            'modal' => ['wctm-admin-utils'],
            'navigation' => ['wctm-admin-utils'],
            'dashboard' => ['wctm-admin-utils'],
            'connection' => ['wctm-admin-utils', 'wp-i18n'],
            'notifications' => ['wctm-admin-utils'],
            'logs' => ['wctm-admin-utils'],
            'system' => ['wctm-admin-utils'],
            'app' => ['wctm-admin-utils'],
        ];

        foreach ($jsFiles as $file => $deps) {
            wp_enqueue_script(
                'wctm-admin-' . $file,
                $this->pluginUrl . 'assets/admin/js/' . $file . '.js',
                $deps,
                $this->version,
                true
            );
        }

        wp_localize_script('wctm-admin-utils', 'wctmAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wctm_admin_nonce'),
        ]);

        foreach (array_keys($jsFiles) as $file) {
            wp_set_script_translations('wctm-admin-' . $file, 'telegram-manager-woocommerce', WCTM_PATH . 'languages');
        }
    }
}
