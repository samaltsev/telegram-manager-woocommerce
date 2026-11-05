<?php
declare(strict_types=1);

namespace WCTM\Bootstrap\Providers;

use WCTM\Admin\Admin;
use WCTM\Admin\AdminAssets;
use WCTM\Admin\DashboardPageController;
use WCTM\Admin\LogsPageController;
use WCTM\Admin\ConnectionPageController;
use WCTM\Admin\SystemInfoPageController;
use WCTM\Ajax\AjaxController;
use WCTM\Ajax\BotAjaxController;
use WCTM\Ajax\LogsAjaxController;
use WCTM\Ajax\SettingsAjaxController;
use WCTM\Bootstrap\Container;
use WCTM\Bootstrap\HooksRegistrar;
use WCTM\Bootstrap\ServiceProviderInterface;
use WCTM\Database\LoggerRepository;
use WCTM\Logger\Logger;
use WCTM\Logger\LoggerService;
use WCTM\Support\SystemInfo;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void
    {
        $container->set('logger.repo', static fn() => new LoggerRepository());
        $container->set('logger.service', static fn($c) => new LoggerService($c->get('logger.repo')));
        Logger::setService($container->get('logger.service'));

        $container->set('system.info', static fn($c) => new SystemInfo(
            $c->get('settings.bots'),
            $c->get('queue.repo')
        ));

        $container->set('admin.page.dashboard', static fn($c) => new DashboardPageController(
            $c->get('logger.repo'),
            $c->get('queue.repo')
        ));
        $container->set('admin.page.logs', static fn($c) => new LogsPageController(
            $c->get('logger.repo'),
            $c->get('queue.repo')
        ));
        $container->set('admin.page.connection', static fn($c) => new ConnectionPageController(
            $c->get('settings')
        ));
        $container->set('admin.page.system_info', static fn($c) => new SystemInfoPageController(
            $c->get('system.info')
        ));

        $container->set('admin', static fn($c) => new Admin(
            $c->get('settings'),
            $c->get('admin.page.dashboard'),
            $c->get('admin.page.connection'),
            $c->get('admin.page.logs'),
            $c->get('admin.page.system_info')
        ));
        $container->set('admin.assets', static fn() => new AdminAssets(WCTM_URL, WCTM_VERSION));

        $container->set('ajax.bot', static fn($c) => new BotAjaxController(
            $c->get('settings.bots'),
            $c->get('telegram.client'),
            $c->get('message.sender'),
            $c->get('telegram.connection_tester'),
            $c->get('bot.options')
        ));
        $container->set('ajax.settings', static fn($c) => new SettingsAjaxController(
            $c->get('settings.bots'),
            $c->get('settings.templates'),
            $c->get('template.parser')
        ));
        $container->set('ajax.logs', static fn($c) => new LogsAjaxController(
            $c->get('settings.bots'),
            $c->get('logger.repo'),
            $c->get('queue.repo')
        ));
        $container->set('ajax', static fn($c) => new AjaxController(
            $c->get('ajax.bot'),
            $c->get('ajax.settings'),
            $c->get('ajax.logs')
        ));

        $admin = $container->get('admin');
        $ajax = $container->get('ajax');

        $hooks->action('admin_menu', $admin, 'registerMenu');
        $hooks->action('admin_init', $admin, 'registerSettings');
        $hooks->action('admin_enqueue_scripts', $container->get('admin.assets'), 'enqueue');
        $hooks->action('admin_notices', $admin, 'renderAdminNotices');

        foreach ([
            'verify_bot',
            'test_connection',
            'run_diagnostics',
            'save_bot_settings',
            'refresh_webhook',
            'check_webhook',
            'get_chat_id',
            'save_templates',
            'preview_template',
            'clear_logs',
            'clear_success_logs',
            'save_log_settings',
            'clear_stuck_jobs',
            'retry_failed_jobs',
            'clear_failed_jobs',
            'retry_failed_job',
            'failed_job_details',
            'clear_undelivered_jobs',
            'cleanup_old_records',
        ] as $action) {
            $hooks->action('wp_ajax_wctm_' . $action, $ajax, $action);
        }
    }
}
