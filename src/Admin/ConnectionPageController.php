<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ConnectionPageController
{
    public function __construct(private SettingsOptionRepository $settings)
    {
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }

        $settings = $this->settings->all();
        $adminBotName = trim((string) ($settings['bot_name'] ?? ''));
        $adminBotUsername = trim((string) ($settings['bot_username'] ?? ''));
        $adminChatId = trim((string) ($settings['admin_chat_id'] ?? ''));
        $adminBotLink = $adminBotUsername !== '' ? 'https://t.me/' . rawurlencode(ltrim($adminBotUsername, '@')) : '';
        $adminBotSetup = [
            'hasToken' => trim((string) ($settings['bot_token'] ?? '')) !== '',
            'hasUsername' => $adminBotUsername !== '',
            'hasChatId' => $adminChatId !== '',
        ];

        ViewRenderer::render('connection', compact('settings', 'adminBotName', 'adminBotUsername', 'adminChatId', 'adminBotLink', 'adminBotSetup'));
    }
}
