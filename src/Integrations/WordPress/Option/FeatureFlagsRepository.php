<?php
declare(strict_types=1);
namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

class FeatureFlagsRepository
{
    const OPTION_KEY = 'wctm_settings';

    public function defaults()
    {
        return array(
            'notify_clients' => 0,
            'admin_bot_commands' => 0,
            'daily_reports' => 0,
            'revenue_reports' => 0,
            'low_stock_alerts' => 0,
            'telegram_crm_cards' => 0,
            'message_action_buttons' => 0,
            'daily_report_enabled' => 0,
            'daily_report_time' => '09:00',
            'daily_report_chat_id' => '',
            'low_stock_threshold' => 5,
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

        foreach (array_keys($this->defaults()) as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            if ($key === 'daily_report_time') {
                $value = sanitize_text_field(wp_unslash((string) $input[$key]));
                $out[$key] = preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value) ? $value : '09:00';
                continue;
            }

            if ($key === 'daily_report_chat_id') {
                $out[$key] = preg_replace('/[^0-9\-]/', '', wp_unslash((string) $input[$key]));
                continue;
            }

            if ($key === 'low_stock_threshold') {
                $out[$key] = max(0, (int) $input[$key]);
                continue;
            }

            $out[$key] = !empty($input[$key]) ? 1 : 0;
        }

        return array_intersect_key($out, $this->defaults());
    }
}
