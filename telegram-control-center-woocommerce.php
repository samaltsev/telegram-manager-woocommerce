<?php
declare(strict_types=1);

/**
 * Plugin Name: Telegram Control Center for WooCommerce
 * Description: Telegram Control Center for WooCommerce sends reliable Telegram order notifications for WooCommerce stores.
 * Version: 2.1.16
 * Author: Phoenix Group
 * Text Domain: telegram-manager-woocommerce
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

defined('WCTM_VERSION') || define('WCTM_VERSION', '2.1.16');
defined('WCTM_DB_VERSION') || define('WCTM_DB_VERSION', '13');
defined('WCTM_FILE') || define('WCTM_FILE', __FILE__);
defined('WCTM_PATH') || define('WCTM_PATH', plugin_dir_path(__FILE__));
defined('WCTM_URL') || define('WCTM_URL', plugin_dir_url(__FILE__));
defined('WCTM_BASENAME') || define('WCTM_BASENAME', plugin_basename(__FILE__));

$autoloader = WCTM_PATH . 'src/Autoloader.php';

if (!file_exists($autoloader)) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>'
            . esc_html__('Telegram Control Center for WooCommerce: Autoloader file is missing.', 'telegram-manager-woocommerce')
            . '</p></div>';
    });

    return;
}

require_once $autoloader;

if (!class_exists('\\WCTM\\Autoloader')) {
    add_action('admin_notices', static function (): void {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>'
            . esc_html__('Telegram Control Center for WooCommerce: Autoloader class was not found.', 'telegram-manager-woocommerce')
            . '</p></div>';
    });

    return;
}

\WCTM\Autoloader::register();

register_activation_hook(WCTM_FILE, ['\\WCTM\\Core\\Activator', 'activate']);
register_deactivation_hook(WCTM_FILE, ['\\WCTM\\Core\\Deactivator', 'deactivate']);

add_action('before_woocommerce_init', static function (): void {
    if (!class_exists('\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        WCTM_FILE,
        true
    );
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('\\WCTM\\Core\\Activator')) {
        return;
    }

    \WCTM\Core\Activator::maybeUpgrade();

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-warning"><p>'
                . esc_html__(
                    'Telegram Control Center for WooCommerce requires WooCommerce to be installed and active.',
                    'telegram-manager-woocommerce'
                )
                . '</p></div>';
        });

        return;
    }

    if (!class_exists('\\WCTM\\Bootstrap\\Plugin')) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>'
                . esc_html__(
                    'Telegram Control Center for WooCommerce: Bootstrap class was not found.',
                    'telegram-manager-woocommerce'
                )
                . '</p></div>';
        });

        return;
    }

    (new \WCTM\Bootstrap\Plugin())->run();
});
