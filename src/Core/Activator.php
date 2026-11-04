<?php
declare(strict_types=1);

namespace WCTM\Core;

use Throwable;
use WCTM\Database\Schema;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\FeatureFlagsRepository;
use WCTM\Integrations\WordPress\Option\NotificationSettingsRepository;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;
use WCTM\Integrations\WordPress\Option\SettingsOptionRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Licensing\FeatureGate;
use WCTM\Licensing\LicenseManager;
use WCTM\Logger\Logger;
use WCTM\Queue\QueueScheduler;
use WCTM\AdminBot\Scheduler\DailyReportScheduler;
use WCTM\AdminBot\Scheduler\LowStockScheduler;

if (!defined('ABSPATH')) {
    exit;
}

class Activator
{
    public static function activate(): void
    {
        self::installOrUpgrade();
        flush_rewrite_rules();
    }

    public static function maybeUpgrade(): void
    {
        $installed = (string) get_option('wctm_db_version', '0');

        if ($installed !== (string) WCTM_DB_VERSION) {
            self::installOrUpgrade();
        }
    }

    private static function installOrUpgrade(): void
    {
        try {
            Schema::createTables();
            update_option('wctm_db_version', WCTM_DB_VERSION);

            $settingsRepository = new SettingsOptionRepository(
                new BotSettingsRepository(),
                new NotificationSettingsRepository(),
                new QueueSettingsRepository(),
                new TemplateSettingsRepository(),
                new FeatureFlagsRepository()
            );

            $currentSettings = (array) get_option('wctm_settings', []);
            $mergedSettings = wp_parse_args($currentSettings, $settingsRepository->defaults());

            update_option('wctm_settings', $mergedSettings);

            if (!get_option('wctm_webhook_secret')) {
                update_option(
                    'wctm_webhook_secret',
                    wp_generate_password(32, false, false),
                    false
                );
            }

            if (get_option('wctm_last_webhook_hit', null) === null) {
                add_option('wctm_last_webhook_hit', 'never', '', false);
            }

            if (get_option('wctm_queue_last_result', null) === null) {
                add_option('wctm_queue_last_result', '', '', false);
            }

            if (get_option('wctm_hide_success_logs', null) === null) {
                add_option('wctm_hide_success_logs', true, '', false);
            }

            if (get_option('wctm_store_success_logs', null) === null) {
                add_option('wctm_store_success_logs', false, '', false);
            }

            if (get_option('wctm_log_retention_days', null) === null) {
                add_option('wctm_log_retention_days', 7, '', false);
            }

            QueueScheduler::scheduleRecurring();
            self::syncProCron(
                new FeatureGate(
                    new LicenseManager(),
                    new FeatureFlagsRepository()
                )
            );
        } catch (Throwable $e) {
            if (class_exists(Logger::class)) {
                Logger::log('error', 'activation', 'Plugin activation failed', [
                    'message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    private static function syncProCron(FeatureGate $features): void
    {
        if ($features->allows(FeatureGate::DAILY_REPORTS)) {
            DailyReportScheduler::schedule();
        } else {
            DailyReportScheduler::unschedule();
        }

        if ($features->allows(FeatureGate::LOW_STOCK_ALERTS)) {
            LowStockScheduler::schedule();
        } else {
            LowStockScheduler::unschedule();
        }
    }
}
