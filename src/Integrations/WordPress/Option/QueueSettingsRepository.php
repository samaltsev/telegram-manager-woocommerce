<?php
declare(strict_types=1);
namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

class QueueSettingsRepository
{
    const OPTION_KEY = 'wctm_settings';

    public function defaults()
    {
        return array(
            'max_attempts' => 3,
            'retry_delay' => 300,
            'fast_dispatch' => 1,
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

        foreach (array('max_attempts', 'retry_delay') as $key) {
            if (isset($input[$key])) {
                $out[$key] = max(0, absint($input[$key]));
            }
        }

        if (array_key_exists('fast_dispatch', $input)) {
            $out['fast_dispatch'] = !empty($input['fast_dispatch']) ? 1 : 0;
        }

        return array_intersect_key($out, $this->defaults());
    }
}
