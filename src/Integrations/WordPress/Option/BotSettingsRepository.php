<?php
declare(strict_types=1);

namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

final class BotSettingsRepository
{
    public const OPTION_KEY = 'wctm_settings';

    public function defaults(): array
    {
        return ['bot_token' => '', 'bot_name' => '', 'bot_username' => '', 'admin_chat_id' => '', 'chat_targets' => ''];
    }

    public function all(): array
    {
        return wp_parse_args((array) get_option(self::OPTION_KEY, []), $this->defaults());
    }

    public function get($key, $default = null)
    {
        $all = $this->all();
        if ($key === 'bot_token' && defined('WCTM_TELEGRAM_BOT_TOKEN') && WCTM_TELEGRAM_BOT_TOKEN !== '') {
            return (string) WCTM_TELEGRAM_BOT_TOKEN;
        }
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function update(array $data): void
    {
        update_option(self::OPTION_KEY, array_merge((array) get_option(self::OPTION_KEY, []), $this->sanitize($data)));
    }

    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $out = [];
        $lockedToken = defined('WCTM_TELEGRAM_BOT_TOKEN') && WCTM_TELEGRAM_BOT_TOKEN !== '';
        foreach ($this->defaults() as $key => $default) {
            if ($lockedToken && $key === 'bot_token') {
                // Never copy a wp-config constant into the database.
                continue;
            }
            $out[$key] = array_key_exists($key, $input)
                ? sanitize_textarea_field(wp_unslash((string) $input[$key]))
                : (string) $this->get($key, $default);
        }
        return $out;
    }
}
