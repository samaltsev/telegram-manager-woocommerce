<?php
declare(strict_types=1);
if (!defined('ABSPATH')) { exit; }

$tables = (array) ($data['tables'] ?? []);
$queue = (array) ($data['queue'] ?? []);
$settings = (array) get_option('wctm_settings', []);
$hasToken = trim((string) ($settings['bot_token'] ?? '')) !== '';
$hasChat = trim((string) ($settings['admin_chat_id'] ?? '')) !== '';
$lastNotification = (string) ($queue['last_done_at'] ?? '');
$lastError = (string) ($queue['last_failed_attempt_at'] ?? '—');
$botImage = defined('WCTM_URL') ? WCTM_URL . 'assets/img/bot.png' : '';

$reportLines = [
    'Plugin: Telegram Control Center for WooCommerce',
    'Plugin version: ' . (string) ($data['plugin_version'] ?? ''),
    'Database version: ' . (string) ($data['db_version'] ?? ''),
    'WordPress: ' . (string) ($data['wp_version'] ?? ''),
    'WooCommerce: ' . (string) ($data['woo_version'] ?? ''),
    'PHP: ' . (string) ($data['php_version'] ?? ''),
    'Memory limit: ' . (string) ($data['memory_limit'] ?? ''),
    'Bot token: ' . ($hasToken ? 'configured' : 'not configured'),
    'Telegram chat: ' . ($hasChat ? 'configured' : 'not configured'),
    'Jobs table: ' . (string) ($tables['jobs'] ?? 'no'),
    'Job attempts table: ' . (string) ($tables['job_attempts'] ?? 'no'),
    'Logs table: ' . (string) ($tables['logs'] ?? 'no'),
];
?>
<div class="wrap wctm-admin wctm-modern-page wctm-system-page">
    <div class="wctm-dash-header">
        <div class="wctm-dash-brand">
            <?php if ($botImage !== '') : ?>
                <img class="wctm-dash-logo" src="<?php echo esc_url($botImage); ?>" alt="" />
            <?php endif; ?>
            <div>
                <h1><?php esc_html_e('System Info', 'telegram-manager-woocommerce'); ?></h1>
                <p><?php esc_html_e('Technical information about your WordPress, WooCommerce and Telegram notification setup.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </div>
        <div class="wctm-dash-actions">
            <button type="button" class="wctm-btn wctm-btn-secondary" id="wctm-copy-system-report" data-report="<?php echo esc_attr(implode("\n", $reportLines)); ?>">
                <?php esc_html_e('Copy system report', 'telegram-manager-woocommerce'); ?>
            </button>
        </div>
    </div>

    <section class="wctm-system-grid">
        <article class="wctm-modern-card wctm-system-card">
            <div class="wctm-card-title-row">
                <span class="wctm-card-icon">🤖</span>
                <h2><?php esc_html_e('Plugin', 'telegram-manager-woocommerce'); ?></h2>
            </div>
            <div class="wctm-system-row"><span><?php esc_html_e('Telegram Control Center', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html('v' . (string) ($data['plugin_version'] ?? '')); ?></strong></div>
            <div class="wctm-system-row"><span><?php esc_html_e('Database version', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) ($data['db_version'] ?? 'n/a')); ?></strong></div>
        </article>

        <article class="wctm-modern-card wctm-system-card">
            <div class="wctm-card-title-row">
                <span class="wctm-card-icon">🌐</span>
                <h2><?php esc_html_e('WordPress Environment', 'telegram-manager-woocommerce'); ?></h2>
            </div>
            <div class="wctm-system-row"><span><?php esc_html_e('WordPress', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) ($data['wp_version'] ?? '')); ?></strong></div>
            <div class="wctm-system-row"><span><?php esc_html_e('WooCommerce', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) ($data['woo_version'] ?? '')); ?></strong></div>
            <div class="wctm-system-row"><span><?php esc_html_e('PHP', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) ($data['php_version'] ?? '')); ?></strong></div>
            <div class="wctm-system-row"><span><?php esc_html_e('Memory limit', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html((string) ($data['memory_limit'] ?? '')); ?></strong></div>
        </article>

        <article class="wctm-modern-card wctm-system-card">
            <div class="wctm-card-title-row">
                <span class="wctm-card-icon">📨</span>
                <h2><?php esc_html_e('Telegram Delivery', 'telegram-manager-woocommerce'); ?></h2>
            </div>
            <div class="wctm-system-row"><span><?php esc_html_e('Bot token', 'telegram-manager-woocommerce'); ?></span><span class="wctm-mini-badge <?php echo esc_attr($hasToken ? 'is-ok' : 'is-error'); ?>"><?php echo esc_html($hasToken ? __('Configured', 'telegram-manager-woocommerce') : __('Not configured', 'telegram-manager-woocommerce')); ?></span></div>
            <div class="wctm-system-row"><span><?php esc_html_e('Telegram chat', 'telegram-manager-woocommerce'); ?></span><span class="wctm-mini-badge <?php echo esc_attr($hasChat ? 'is-ok' : 'is-error'); ?>"><?php echo esc_html($hasChat ? __('Configured', 'telegram-manager-woocommerce') : __('Not configured', 'telegram-manager-woocommerce')); ?></span></div>
            <div class="wctm-system-row"><span><?php esc_html_e('Last notification', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html($lastNotification !== '' ? $lastNotification : '—'); ?></strong></div>
            <div class="wctm-system-row"><span><?php esc_html_e('Last error', 'telegram-manager-woocommerce'); ?></span><strong><?php echo esc_html($lastError !== '' ? $lastError : '—'); ?></strong></div>
        </article>

        <article class="wctm-modern-card wctm-system-card">
            <div class="wctm-card-title-row">
                <span class="wctm-card-icon">🗄</span>
                <h2><?php esc_html_e('Database Tables', 'telegram-manager-woocommerce'); ?></h2>
            </div>
            <?php foreach ([
                'jobs' => __('Jobs table', 'telegram-manager-woocommerce'),
                'job_attempts' => __('Job attempts table', 'telegram-manager-woocommerce'),
                'logs' => __('Logs table', 'telegram-manager-woocommerce'),
            ] as $key => $label) :
                $ok = (($tables[$key] ?? 'no') === 'yes');
            ?>
                <div class="wctm-system-row"><span><?php echo esc_html($label); ?></span><span class="wctm-mini-badge <?php echo esc_attr($ok ? 'is-ok' : 'is-error'); ?>"><?php echo esc_html($ok ? __('Available', 'telegram-manager-woocommerce') : __('Missing', 'telegram-manager-woocommerce')); ?></span></div>
            <?php endforeach; ?>
        </article>
    </section>
</div>
