<?php
declare(strict_types=1);

namespace WCTM\Licensing;

use WCTM\Integrations\WordPress\Option\FeatureFlagsRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class FeatureGate
{
    public const ORDER_NOTIFICATIONS = 'order_notifications';
    public const STATUS_NOTIFICATIONS = 'status_notifications';
    public const BASIC_TEMPLATES = 'basic_templates';
    public const SINGLE_ADMIN_CHAT = 'single_admin_chat';
    public const QUEUE_RETRY = 'queue_retry';
    public const DELIVERY_LOGS = 'delivery_logs';
    public const SYSTEM_INFO = 'system_info';

    public const ADMIN_BOT_COMMANDS = 'admin_bot_commands';
    public const DAILY_REPORTS = 'daily_reports';
    public const REVENUE_REPORTS = 'revenue_reports';
    public const LOW_STOCK_ALERTS = 'low_stock_alerts';
    public const TELEGRAM_CRM_CARDS = 'telegram_crm_cards';
    public const MESSAGE_ACTION_BUTTONS = 'message_action_buttons';

    /** @var array<int, string> */
    private array $freeFeatures = [
        self::ORDER_NOTIFICATIONS,
        self::STATUS_NOTIFICATIONS,
        self::BASIC_TEMPLATES,
        self::SINGLE_ADMIN_CHAT,
        self::QUEUE_RETRY,
        self::DELIVERY_LOGS,
        self::SYSTEM_INFO,
    ];

    /** @var array<int, string> */
    private array $proFeatures = [
        self::ADMIN_BOT_COMMANDS,
        self::DAILY_REPORTS,
        self::REVENUE_REPORTS,
        self::LOW_STOCK_ALERTS,
        self::TELEGRAM_CRM_CARDS,
        self::MESSAGE_ACTION_BUTTONS,
    ];

    public function __construct(
        private LicenseManager $licenseManager,
        private FeatureFlagsRepository $features
    ) {}

    public function allows(string $feature): bool
    {
        $feature = sanitize_key($feature);

        if (in_array($feature, $this->freeFeatures, true)) {
            return true;
        }

        if (!$this->licenseManager->isPro()) {
            return false;
        }

        return (bool) $this->features->get($feature, 0);
    }

    public function isFreeFeature(string $feature): bool
    {
        return in_array(sanitize_key($feature), $this->freeFeatures, true);
    }

    public function isProFeature(string $feature): bool
    {
        return in_array(sanitize_key($feature), $this->proFeatures, true);
    }

    /**
     * @return array<int, string>
     */
    public function proFeatures(): array
    {
        return $this->proFeatures;
    }
}
