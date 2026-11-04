<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}

/**
 * System Info view.
 *
 * All values are prepared in WCTM\Support\SystemInfo and passed by ViewRenderer
 * as flat variables. The small fallbacks below keep the view safe if an older
 * controller still passes a nested $data array.
 */
$source = isset($data) && is_array($data) ? $data : get_defined_vars();

$plugin = (array) ($source['plugin'] ?? []);
$environment = (array) ($source['environment'] ?? []);
$telegram = (array) ($source['telegram'] ?? []);
$tables = (array) ($source['tables'] ?? []);
$queue = (array) ($source['queue'] ?? []);

$pluginName = (string) ($plugin['name'] ?? 'Telegram Control Center');
$pluginVersion = (string) ($plugin['version'] ?? ($source['plugin_version'] ?? 'n/a'));
$dbVersion = (string) ($plugin['db_version'] ?? ($source['db_version'] ?? 'n/a'));

$wpVersion = (string) ($environment['wp_version'] ?? ($source['wp_version'] ?? 'n/a'));
$wooVersion = (string) ($environment['woo_version'] ?? ($source['woo_version'] ?? 'n/a'));
$phpVersion = (string) ($environment['php_version'] ?? ($source['php_version'] ?? PHP_VERSION));
$memoryLimit = (string) ($environment['memory_limit'] ?? ($source['memory_limit'] ?? ini_get('memory_limit')));
$debugMode = !empty($environment['debug_mode']);

$hasToken = !empty($telegram['bot_token_configured']);
$hasChat = !empty($telegram['chat_configured']);
$chatId = (string) ($telegram['chat_id'] ?? '');
$botUsername = (string) ($telegram['bot_username'] ?? '');
$botName = (string) ($telegram['bot_name'] ?? '');
$lastNotification = (string) ($telegram['last_notification'] ?? ($queue['last_done_at'] ?? ''));
$lastError = (string) ($telegram['last_error'] ?? ($queue['last_failed_attempt_at'] ?? ''));

$title = __('System Info', 'telegram-manager-woocommerce');
$subtitle = __('Technical information about your WordPress, WooCommerce and Telegram notification setup.', 'telegram-manager-woocommerce');
$actions = [
    ['type' => 'text', 'label' => 'v' . $pluginVersion],
];

$statusBadge = static function (bool $ok, string $okText, string $badText, string $badClass = 'is-warning'): void {
    echo '<span class="tcc-mini-badge ' . esc_attr($ok ? 'is-success' : $badClass) . '">' . esc_html($ok ? $okText : $badText) . '</span>';
};

$displayValue = static function (string $value): string {
    $value = trim($value);
    return $value !== '' ? $value : '—';
};

$tableExists = static function (array $table): bool {
    if (array_key_exists('exists', $table)) {
        return (bool) $table['exists'];
    }

    return (($table['status'] ?? '') === 'yes') || (($table['available'] ?? '') === 'yes');
};

$tableRows = static function (array $table): string {
    if (!array_key_exists('rows', $table) || $table['rows'] === null) {
        return '';
    }

    return ' · ' . sprintf(
        /* translators: %d: database rows count */
        _n('%d row', '%d rows', (int) $table['rows'], 'telegram-manager-woocommerce'),
        (int) $table['rows']
    );
};

$reportLines = [
    'Plugin: ' . $pluginName,
    'Plugin version: ' . $pluginVersion,
    'Database version: ' . $dbVersion,
    'WordPress: ' . $wpVersion,
    'WooCommerce: ' . $wooVersion,
    'PHP: ' . $phpVersion,
    'Memory limit: ' . $memoryLimit,
    'Debug mode: ' . ($debugMode ? 'yes' : 'no'),
    'Bot token: ' . ($hasToken ? 'configured' : 'not configured'),
    'Telegram chat: ' . ($hasChat ? 'configured' : 'not configured'),
    'Telegram chat ID: ' . ($chatId !== '' ? $chatId : 'n/a'),
    'Bot username: ' . ($botUsername !== '' ? $botUsername : 'n/a'),
    'Last notification: ' . ($lastNotification !== '' ? $lastNotification : 'n/a'),
    'Last error: ' . ($lastError !== '' ? $lastError : 'none'),
];

foreach ($tables as $table) {
    if (!is_array($table)) {
        continue;
    }

    $reportLines[] = (string) ($table['label'] ?? $table['name'] ?? 'Table') . ': ' . ($tableExists($table) ? 'available' : 'missing') . ' (' . (string) ($table['name'] ?? '') . ')';
}

include WCTM_PATH . 'views/layout-start.php';
include WCTM_PATH . 'views/header.php';
?>

<div class="tcc-page-content tcc-system-page">
    <div class="tcc-actions-row tcc-mb-3">
        <button
            type="button"
            class="tcc-btn tcc-btn-secondary"
            id="wctm-copy-system-report"
            data-report="<?php echo esc_attr(implode("\n", $reportLines)); ?>"
        >
            <?php esc_html_e('Copy system report', 'telegram-manager-woocommerce'); ?>
        </button>
    </div>

    <section class="tcc-system-grid">
        <article class="tcc-card tcc-system-card">
            <div class="tcc-card-title-row">
                <span aria-hidden="true">🤖</span>
                <h2><?php esc_html_e('Plugin', 'telegram-manager-woocommerce'); ?></h2>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Name', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($pluginName); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Version', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html('v' . $pluginVersion); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Database version', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($dbVersion); ?></strong>
            </div>
        </article>

        <article class="tcc-card tcc-system-card">
            <div class="tcc-card-title-row">
                <span aria-hidden="true">🌐</span>
                <h2><?php esc_html_e('WordPress Environment', 'telegram-manager-woocommerce'); ?></h2>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('WordPress', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($wpVersion); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('WooCommerce', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($wooVersion); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('PHP', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($phpVersion); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Memory limit', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($memoryLimit); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Debug mode', 'telegram-manager-woocommerce'); ?></span>
                <?php $statusBadge(!$debugMode, __('Off', 'telegram-manager-woocommerce'), __('On', 'telegram-manager-woocommerce'), 'is-warning'); ?>
            </div>
        </article>

        <article class="tcc-card tcc-system-card">
            <div class="tcc-card-title-row">
                <span aria-hidden="true">📨</span>
                <h2><?php esc_html_e('Telegram Delivery', 'telegram-manager-woocommerce'); ?></h2>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Bot token', 'telegram-manager-woocommerce'); ?></span>
                <?php $statusBadge($hasToken, __('Configured', 'telegram-manager-woocommerce'), __('Not configured', 'telegram-manager-woocommerce')); ?>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Telegram chat', 'telegram-manager-woocommerce'); ?></span>
                <?php $statusBadge($hasChat, __('Configured', 'telegram-manager-woocommerce'), __('Not configured', 'telegram-manager-woocommerce')); ?>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Chat ID', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($displayValue($chatId)); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Bot username', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($displayValue($botUsername !== '' ? '@' . ltrim($botUsername, '@') : '')); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Last notification', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($displayValue($lastNotification)); ?></strong>
            </div>

            <div class="tcc-system-row">
                <span><?php esc_html_e('Last error', 'telegram-manager-woocommerce'); ?></span>
                <strong><?php echo esc_html($displayValue($lastError)); ?></strong>
            </div>
        </article>

        <article class="tcc-card tcc-system-card">
            <div class="tcc-card-title-row">
                <span aria-hidden="true">🗄</span>
                <h2><?php esc_html_e('Database Tables', 'telegram-manager-woocommerce'); ?></h2>
            </div>

            <?php foreach ($tables as $table) : ?>
                <?php
                if (!is_array($table)) {
                    continue;
                }

                $exists = $tableExists($table);
                $label = (string) ($table['label'] ?? $table['name'] ?? '');
                $name = (string) ($table['name'] ?? '');
                ?>
                <div class="tcc-system-row">
                    <span>
                        <?php echo esc_html($label); ?>
                        <?php if ($name !== '') : ?>
                            <small class="tcc-row-note"><?php echo esc_html($name . $tableRows($table)); ?></small>
                        <?php endif; ?>
                    </span>
                    <?php $statusBadge($exists, __('Available', 'telegram-manager-woocommerce'), __('Missing', 'telegram-manager-woocommerce')); ?>
                </div>
            <?php endforeach; ?>
        </article>
    </section>

    <section class="tcc-card tcc-diagnostics-card tcc-mt-3">
        <div class="tcc-card-title-row">
            <span aria-hidden="true">✅</span>
            <h2><?php esc_html_e('Diagnostics', 'telegram-manager-woocommerce'); ?></h2>
        </div>

        <div class="tcc-diagnostics-grid">
            <div><?php $statusBadge($wpVersion !== 'n/a', __('WordPress OK', 'telegram-manager-woocommerce'), __('WordPress unknown', 'telegram-manager-woocommerce')); ?></div>
            <div><?php $statusBadge($wooVersion !== 'not active' && $wooVersion !== 'n/a', __('WooCommerce OK', 'telegram-manager-woocommerce'), __('WooCommerce not active', 'telegram-manager-woocommerce')); ?></div>
            <div><?php $statusBadge(version_compare(PHP_VERSION, '8.1', '>='), __('PHP OK', 'telegram-manager-woocommerce'), __('PHP too old', 'telegram-manager-woocommerce')); ?></div>
            <div><?php $statusBadge($hasToken && $hasChat, __('Telegram OK', 'telegram-manager-woocommerce'), __('Telegram not ready', 'telegram-manager-woocommerce')); ?></div>
            <div><?php $statusBadge(empty($queue['failed']), __('Queue OK', 'telegram-manager-woocommerce'), __('Queue has failures', 'telegram-manager-woocommerce'), 'is-error'); ?></div>
            <div><?php $statusBadge(count(array_filter($tables, static fn ($table): bool => is_array($table) && $tableExists($table))) === count($tables), __('Database OK', 'telegram-manager-woocommerce'), __('Database incomplete', 'telegram-manager-woocommerce'), 'is-warning'); ?></div>
        </div>
    </section>
</div>

<?php include WCTM_PATH . 'views/layout-end.php'; ?>
