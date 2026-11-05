<?php
declare(strict_types=1);
namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

class NotificationSettingsRepository
{
    const OPTION_KEY = 'wctm_settings';

    public function defaults()
    {
        return array(
            'notify_new_order' => 1,
            'show_new_order_stock' => 0,
            'notify_status_changed' => 1,
            'notify_refund_created' => 1,
            'notify_payment_received' => 0,
            'status_whitelist' => 'pending processing on-hold completed cancelled failed refunded',
            'notify_status_pending' => 1,
            'notify_status_processing' => 1,
            'notify_status_on-hold' => 1,
            'notify_status_completed' => 1,
            'notify_status_cancelled' => 1,
            'notify_status_failed' => 1,
            'notify_status_refunded' => 1,
            'notify_product_instock' => 1,
            'notify_product_outofstock' => 1,
            'notify_product_onbackorder' => 1,
            'notify_product_lowstock' => 1,
            'notify_product_quantity' => 0,
            'notify_product_publication' => 0,
        );
    }

    public function all()
    {
        $all = (array) get_option(self::OPTION_KEY, array());
        return wp_parse_args($all, $this->defaults());
    }

    public function get($key, $default = null)
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function update(array $data)
    {
        $all = (array) get_option(self::OPTION_KEY, array());
        update_option(self::OPTION_KEY, array_merge($all, $this->sanitize($data)));
    }

    public function sanitize($input)
    {
        $input = is_array($input) ? $input : array();
        $out = $this->all();

        foreach (array('show_new_order_stock', 'notify_new_order', 'notify_status_changed', 'notify_refund_created', 'notify_payment_received', 'notify_product_instock', 'notify_product_outofstock', 'notify_product_onbackorder', 'notify_product_lowstock', 'notify_product_quantity', 'notify_product_publication') as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = !empty($input[$key]) ? 1 : 0;
            }
        }

        // Registered custom WooCommerce statuses get independent switches as well.
        if (function_exists('wc_get_order_statuses')) {
            foreach (array_keys((array) wc_get_order_statuses()) as $wcStatus) {
                $slug = sanitize_key(preg_replace('/^wc-/', '', (string) $wcStatus));
                $key = 'notify_status_' . $slug;
                if (array_key_exists($key, $input)) {
                    $out[$key] = !empty($input[$key]) ? 1 : 0;
                }
            }
        }
        if (isset($input['status_whitelist'])) {
            $out['status_whitelist'] = sanitize_textarea_field(wp_unslash($input['status_whitelist']));
        }

        $keys = $this->defaults();
        if (function_exists('wc_get_order_statuses')) {
            foreach (array_keys((array) wc_get_order_statuses()) as $wcStatus) {
                $slug = sanitize_key(preg_replace('/^wc-/', '', (string) $wcStatus));
                $keys['notify_status_' . $slug] = 0;
            }
        }
        return array_intersect_key($out, $keys);
    }

    public function statusWhitelist()
    {
        $raw = $this->get('status_whitelist', '');
        $parts = preg_split('/[\s,;]+/', (string) $raw);
        return array_values(array_filter(array_unique(array_map('sanitize_key', $parts))));
    }
}
