<?php
declare(strict_types=1);

namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

final class BotOptionRepository
{
    public function __construct(
        private BotSettingsRepository $bots,
        private TemplateSettingsRepository $templates
    ) {
    }

    public function getDefaultToken(): string { return $this->getAdminBotToken(); }
    public function getDefaultUsername(): string { return $this->getAdminBotUsername(); }
    public function getAdminBotToken(): string { return (string) $this->bots->get('bot_token', ''); }
    public function getAdminBotUsername(): string { return (string) $this->bots->get('bot_username', ''); }
    public function getAdminChatId(): string { return (string) $this->bots->get('admin_chat_id', ''); }
    public function getChatIdForType(string $botType): string { return trim($this->getAdminChatId()); }
    public function getTokenForType(string $botType): string { return trim($this->getAdminBotToken()); }
    public function getParseMode(): string { return (string) $this->templates->get('parse_mode', 'HTML'); }
    public function disableWebPreview(): bool { return !empty($this->templates->get('disable_web_page_preview', 0)); }
    public function getWebhookSecret(): string { return (string) get_option('wctm_webhook_secret', ''); }
}
