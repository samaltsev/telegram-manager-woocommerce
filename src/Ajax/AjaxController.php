<?php
declare(strict_types=1);

namespace WCTM\Ajax;

if (!defined('ABSPATH')) {
    exit;
}

final class AjaxController
{
    public function __construct(
        private BotAjaxController $botController,
        private SettingsAjaxController $settingsController,
        private LogsAjaxController $logsController
    ) {
    }

    public function verify_bot(): void { $this->botController->verify_bot(); }
    public function test_connection(): void { $this->botController->test_connection(); }
    public function run_diagnostics(): void { $this->botController->run_diagnostics(); }
    public function save_bot_settings(): void { $this->botController->save_bot_settings(); }
    public function refresh_webhook(): void { $this->botController->refresh_webhook(); }
    public function check_webhook(): void { $this->botController->check_webhook(); }
    public function get_chat_id(): void { $this->botController->get_chat_id(); }

    public function save_templates(): void { $this->settingsController->save_templates(); }
    public function preview_template(): void { $this->settingsController->preview_template(); }

    public function clear_logs(): void { $this->logsController->clear_logs(); }
    public function clear_success_logs(): void { $this->logsController->clear_success_logs(); }
    public function save_log_settings(): void { $this->logsController->save_log_settings(); }
    public function clear_stuck_jobs(): void { $this->logsController->clear_stuck_jobs(); }
    public function retry_failed_jobs(): void { $this->logsController->retry_failed_jobs(); }
    public function clear_failed_jobs(): void { $this->logsController->clear_failed_jobs(); }
    public function retry_failed_job(): void { $this->logsController->retry_failed_job(); }
    public function failed_job_details(): void { $this->logsController->failed_job_details(); }
    public function clear_undelivered_jobs(): void { $this->logsController->clear_undelivered_jobs(); }
    public function cleanup_old_records(): void { $this->logsController->cleanup_old_records(); }
}
