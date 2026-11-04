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
            'notify_status_changed' => 1,
            'notify_refund_created' => 1,
            'notify_payment_received' => 0,
            'status_whitelist' => 'completed cancelled refunded',
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

        foreach (array('notify_new_order', 'notify_status_changed', 'notify_refund_created', 'notify_payment_received') as $key) {
            if (array_key_exists($key, $input)) {
                $out[$key] = !empty($input[$key]) ? 1 : 0;
            }
        }

        if (isset($input['status_whitelist'])) {
            $out['status_whitelist'] = sanitize_textarea_field(wp_unslash($input['status_whitelist']));
        }

        return array_intersect_key($out, $this->defaults());
    }

    public function statusWhitelist()
    {
        $raw = $this->get('status_whitelist', '');
        $parts = preg_split('/[\s,;]+/', (string) $raw);
        return array_values(array_filter(array_unique(array_map('sanitize_key', $parts))));
    }
}
