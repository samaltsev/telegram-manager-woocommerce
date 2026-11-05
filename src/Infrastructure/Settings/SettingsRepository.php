<?php
declare(strict_types=1);

namespace WCTM\Infrastructure\Settings;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsRepository
{
    public function getAdminBotToken(): string
    {
        $settings = (array) get_option('wctm_settings', []);
        return trim((string) ($settings['bot_token'] ?? get_option('bot_token', '')));
    }
}
