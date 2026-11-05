<?php
declare(strict_types=1);

namespace WCTM\Ajax;

use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Security\SecurityPolicy;

if (!defined('ABSPATH')) {
    exit;
}

abstract class AbstractAjaxController
{
    public function __construct(protected BotSettingsRepository $botSettings)
    {
    }

    protected function botType(): string { return 'admin'; }
    protected function settingsChatId(string $botType): string { return (string) $this->botSettings->get('admin_chat_id', ''); }
    protected function settingsUsernameLabel(string $botType): string { return 'Telegram Bot'; }
    protected function guard(): void { SecurityPolicy::enforceAdminAjax(); }
}
