<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}

$settings = (array) ($settings ?? []);
$adminChatId = trim((string) ($adminChatId ?? ($settings['admin_chat_id'] ?? '')));
$adminBotName = trim((string) ($adminBotName ?? ($settings['bot_name'] ?? '')));
$adminBotUsername = trim((string) ($adminBotUsername ?? ($settings['bot_username'] ?? '')));
$hasToken = trim((string) ($settings['bot_token'] ?? '')) !== '';
$hasChatId = $adminChatId !== '';
$lastTest = (string) get_option('wctm_last_test_message', '');
$hasLastTest = $lastTest !== '';
$configured = $hasToken && $hasChatId;
$botUsername = ltrim($adminBotUsername, '@');
$botLink = $botUsername !== '' ? 'https://t.me/' . rawurlencode($botUsername) : '';
$botName = $adminBotName !== '' ? $adminBotName : __('Telegram bot not verified yet', 'telegram-manager-woocommerce');
$botUsernameLabel = $botUsername !== '' ? '@' . $botUsername : '—';

$notifyNewOrder = !empty($settings['notify_new_order']);
$notifyStatus = !empty($settings['notify_status_changed']);
$notifyRefund = !empty($settings['notify_refund_created']);

$title = __('Connection & Notifications', 'telegram-manager-woocommerce');
$subtitle = __('Set up Telegram in three steps and choose WooCommerce notifications.', 'telegram-manager-woocommerce');
$badge = __('Free', 'telegram-manager-woocommerce');
$actions = [
    ['type' => 'text', 'label' => $configured ? __('Configuration saved', 'telegram-manager-woocommerce') : __('Setup required', 'telegram-manager-woocommerce')],
];

$notificationCards = [
    [
        'key' => 'notify_new_order',
        'enabled' => $notifyNewOrder,
        'icon' => '🛒',
        'title' => __('New Order', 'telegram-manager-woocommerce'),
        'description' => __('Receive Telegram notification when customer creates order.', 'telegram-manager-woocommerce'),
        'preview_id' => 'tcc-preview-new-order',
        'preview' => "🛒 New Order #4312\nCustomer: John Smith\nTotal: EUR 120\nPayment: Stripe\nStatus: Processing",
    ],
    [
        'key' => 'notify_status_changed',
        'enabled' => $notifyStatus,
        'icon' => '🔄',
        'title' => __('Order Status Changed', 'telegram-manager-woocommerce'),
        'description' => __('Receive Telegram notification when order status updates.', 'telegram-manager-woocommerce'),
        'preview_id' => 'tcc-preview-status',
        'preview' => "🔄 Order #4312 status changed\nPrevious: Processing\nNew: Completed\nCustomer: John Smith",
    ],
    [
        'key' => 'notify_refund_created',
        'enabled' => $notifyRefund,
        'icon' => '↩',
        'title' => __('Refund Created', 'telegram-manager-woocommerce'),
        'description' => __('Receive Telegram notification when a refund is created.', 'telegram-manager-woocommerce'),
        'preview_id' => 'tcc-preview-refund',
        'preview' => "↩ Refund created\nOrder: #4312\nRefund amount: EUR 20\nReason: Customer request",
    ],
];

$step = static function (string $key, string $label, string $description, bool $done, bool $active, string $number): void {
    $classes = trim(($done ? 'is-done ' : '') . ($active ? 'is-active' : ''));
    echo '<li class="' . esc_attr($classes) . '" data-step="' . esc_attr($key) . '">';
    echo '<span>' . esc_html($done ? '✓' : $number) . '</span>';
    echo '<strong>' . esc_html($label) . '</strong>';
    echo '<small>' . esc_html($description) . '</small>';
    echo '</li>';
};

include WCTM_PATH . 'views/layout-start.php';
include WCTM_PATH . 'views/header.php';
?>

<form method="post" action="options.php" id="wctm-settings-form" class="tcc-page-content tcc-connection-page">
    <?php settings_fields('wctm_settings_group'); ?>

    <ol class="tcc-stepper">
        <?php
        $step('token', __('Step 1 — Bot Token', 'telegram-manager-woocommerce'), __('Paste token from BotFather', 'telegram-manager-woocommerce'), $hasToken, !$hasToken, '1');
        $step('chat_id', __('Step 2 — Chat ID', 'telegram-manager-woocommerce'), __('Connect delivery chat', 'telegram-manager-woocommerce'), $hasChatId, $hasToken && !$hasChatId, '2');
        $step('test_message', __('Step 3 — Test Message', 'telegram-manager-woocommerce'), __('Verify notifications', 'telegram-manager-woocommerce'), $hasLastTest, $hasToken && $hasChatId && !$hasLastTest, '3');
        ?>
    </ol>

    <?php if ($hasLastTest) : ?>
        <section class="tcc-ready-banner">
            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
            <div>
                <strong><?php esc_html_e('Telegram is ready', 'telegram-manager-woocommerce'); ?></strong>
                <p><?php esc_html_e('Your WooCommerce order notifications are now active.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head">
            <div>
                <h2><span class="dashicons dashicons-format-chat tcc-icon-telegram" aria-hidden="true"></span> <?php esc_html_e('Telegram Bot Setup', 'telegram-manager-woocommerce'); ?></h2>
                <p><?php esc_html_e('Configure your Telegram bot connection.', 'telegram-manager-woocommerce'); ?></p>
            </div>
        </div>

        <div class="tcc-form-group">
            <label for="bot-token"><?php esc_html_e('Bot Token', 'telegram-manager-woocommerce'); ?></label>
            <div class="tcc-input-wrap">
                <input type="password" id="bot-token" name="wctm_settings[bot_token]" value="<?php echo esc_attr((string) ($settings['bot_token'] ?? '')); ?>" autocomplete="off" placeholder="<?php esc_attr_e('Enter bot token', 'telegram-manager-woocommerce'); ?>">
                <button type="button" class="tcc-icon-button" id="wctm-toggle-token" aria-label="<?php esc_attr_e('Show or hide token', 'telegram-manager-woocommerce'); ?>">
                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                </button>
            </div>
            <div class="helper">
                <?php
                printf(
                    /* translators: %s: BotFather Telegram link. */
                    wp_kses_post(__('Get your token from %s on Telegram.', 'telegram-manager-woocommerce')),
                    '<a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>'
                );
                ?>
            </div>
            <div class="tcc-token-help">
                <strong><?php esc_html_e('How to get a token:', 'telegram-manager-woocommerce'); ?></strong>
                <span><?php esc_html_e('Open @BotFather, send /newbot, create a bot, then paste the token here.', 'telegram-manager-woocommerce'); ?></span>
            </div>
            <div class="tcc-token-actions">
                <button type="button" class="tcc-btn tcc-btn-secondary" id="wctm-verify-bot">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e('Verify Bot', 'telegram-manager-woocommerce'); ?>
                </button>
                <div id="wctm-verify-bot-result" class="tcc-inline-result"></div>
            </div>
        </div>

        <input type="hidden" id="bot-name" name="wctm_settings[bot_name]" value="<?php echo esc_attr($adminBotName); ?>">
        <input type="hidden" id="bot-username" name="wctm_settings[bot_username]" value="<?php echo esc_attr($botUsername); ?>">

        <div class="tcc-bot-info-panel <?php echo esc_attr($hasToken && $botUsername !== '' ? 'is-connected' : 'is-muted'); ?>" id="wctm-bot-info-panel">
            <div>
                <strong><?php esc_html_e('Bot Name', 'telegram-manager-woocommerce'); ?></strong>
                <?php if ($botLink !== '' && $adminBotName !== '') : ?>
                    <a id="wctm-bot-name-link" href="<?php echo esc_url($botLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($botName); ?></a>
                <?php else : ?>
                    <span id="wctm-bot-name-link"><?php echo esc_html($botName); ?></span>
                <?php endif; ?>
            </div>
            <div>
                <strong><?php esc_html_e('Username', 'telegram-manager-woocommerce'); ?></strong>
                <?php if ($botLink !== '') : ?>
                    <a id="wctm-bot-username-link" href="<?php echo esc_url($botLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($botUsernameLabel); ?></a>
                <?php else : ?>
                    <span id="wctm-bot-username-link"><?php echo esc_html($botUsernameLabel); ?></span>
                <?php endif; ?>
            </div>
            <div><span id="wctm-bot-status-badge" class="tcc-badge <?php echo esc_attr($hasToken && $botUsername !== '' ? 'tcc-badge-success' : 'tcc-badge-warning'); ?>"><?php echo esc_html($hasToken && $botUsername !== '' ? __('Connected', 'telegram-manager-woocommerce') : __('Not verified yet', 'telegram-manager-woocommerce')); ?></span></div>
        </div>

        <div class="tcc-form-group">
            <label for="telegram-chat-id"><?php esc_html_e('Telegram Chat ID', 'telegram-manager-woocommerce'); ?></label>
            <div class="tcc-chat-row">
                <input type="text" id="telegram-chat-id" name="wctm_settings[admin_chat_id]" value="<?php echo esc_attr($adminChatId); ?>" placeholder="<?php esc_attr_e('Chat ID', 'telegram-manager-woocommerce'); ?>">
                <button type="button" class="tcc-btn tcc-btn-secondary tcc-btn-sm" id="wctm-get-chat-id"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e('Get Chat ID', 'telegram-manager-woocommerce'); ?></button>
            </div>
            <div class="helper"><?php esc_html_e('Send any message to the bot first, then click Get Chat ID.', 'telegram-manager-woocommerce'); ?></div>
            <div id="wctm-chat-id-result" class="tcc-inline-result"></div>
            <?php if ($hasChatId) : ?>
                <span class="tcc-badge tcc-badge-success"><?php echo esc_html(sprintf(__('Connected to chat: %s', 'telegram-manager-woocommerce'), $adminChatId)); ?></span>
            <?php endif; ?>
        </div>

        <div class="tcc-test-panel">
            <div>
                <h3><?php esc_html_e('Test Connection', 'telegram-manager-woocommerce'); ?></h3>
                <p class="tcc-text-muted"><?php esc_html_e('Send a test message to make sure everything is working.', 'telegram-manager-woocommerce'); ?></p>
            </div>
            <button type="button" class="tcc-btn tcc-btn-telegram" id="wctm-admin-bot-test"><span class="dashicons dashicons-email-alt" aria-hidden="true"></span> <?php esc_html_e('Send Test Message', 'telegram-manager-woocommerce'); ?></button>
            <div id="wctm-admin-bot-test-result" class="tcc-inline-result"></div>
            <?php if ($hasLastTest) : ?>
                <div class="tcc-test-success"><span class="dashicons dashicons-yes" aria-hidden="true"></span><div><strong><?php esc_html_e('Test message delivered', 'telegram-manager-woocommerce'); ?></strong><small><?php echo esc_html(sprintf(__('Last test: %s', 'telegram-manager-woocommerce'), $lastTest)); ?></small></div></div>
            <?php endif; ?>
        </div>
    </section>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head"><div><h2><?php esc_html_e('Choose Notifications', 'telegram-manager-woocommerce'); ?></h2><p><?php esc_html_e('Select which WooCommerce events should be sent to Telegram.', 'telegram-manager-woocommerce'); ?></p></div></div>
        <div class="tcc-notification-list">
            <?php foreach ($notificationCards as $card) : ?>
                <article class="tcc-notif-card <?php echo esc_attr($card['enabled'] ? 'is-enabled' : 'is-disabled'); ?>">
                    <div class="tcc-notif-icon"><?php echo esc_html((string) $card['icon']); ?></div>
                    <div class="tcc-notif-body">
                        <div class="tcc-notif-head">
                            <div><h3><?php echo esc_html((string) $card['title']); ?></h3><p><?php echo esc_html((string) $card['description']); ?></p></div>
                            <label class="tcc-toggle">
                                <input type="hidden" name="wctm_settings[<?php echo esc_attr((string) $card['key']); ?>]" value="0">
                                <input class="tcc-notification-toggle" type="checkbox" name="wctm_settings[<?php echo esc_attr((string) $card['key']); ?>]" value="1" <?php checked((bool) $card['enabled']); ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <button type="button" class="tcc-preview-toggle" data-wctm-preview="<?php echo esc_attr((string) $card['preview_id']); ?>"><?php esc_html_e('Preview', 'telegram-manager-woocommerce'); ?> v</button>
                        <div id="<?php echo esc_attr((string) $card['preview_id']); ?>" class="tcc-telegram-preview" hidden>
                            <div class="tp-header"><span class="avatar"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span></span><strong><?php echo esc_html($botUsernameLabel !== '—' ? $botUsernameLabel : __('Telegram Shop Bot', 'telegram-manager-woocommerce')); ?></strong><span class="time"><?php esc_html_e('Today 14:28', 'telegram-manager-woocommerce'); ?></span></div>
                            <pre><?php echo esc_html((string) $card['preview']); ?></pre>
                            <div class="delivered">✓✓ <?php esc_html_e('Delivered', 'telegram-manager-woocommerce'); ?></div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="tcc-card tcc-mb-3">
        <div class="tcc-section-head"><div><h2><?php esc_html_e('Queue Settings', 'telegram-manager-woocommerce'); ?></h2><p><?php esc_html_e('Configure how the delivery queue works.', 'telegram-manager-woocommerce'); ?></p></div></div>
        <div class="tcc-queue-settings">
            <label class="tcc-setting-card" for="wctm-max-attempts"><span><?php esc_html_e('Retry attempts', 'telegram-manager-woocommerce'); ?></span><input type="number" min="1" max="10" id="wctm-max-attempts" name="wctm_settings[max_attempts]" value="<?php echo esc_attr((string) ($settings['max_attempts'] ?? 3)); ?>"><small class="helper"><?php esc_html_e('How many times to retry failed jobs.', 'telegram-manager-woocommerce'); ?></small></label>
            <label class="tcc-setting-card" for="wctm-retry-delay"><span><?php esc_html_e('Retry delay', 'telegram-manager-woocommerce'); ?></span><input type="number" min="30" id="wctm-retry-delay" name="wctm_settings[retry_delay]" value="<?php echo esc_attr((string) ($settings['retry_delay'] ?? 300)); ?>"><small class="helper"><?php esc_html_e('Delay between retry attempts in seconds.', 'telegram-manager-woocommerce'); ?></small></label>
            <div class="tcc-setting-card tcc-setting-toggle"><div><span><?php esc_html_e('Immediate delivery', 'telegram-manager-woocommerce'); ?></span><small class="helper"><?php esc_html_e('Send immediately after adding to the queue when possible.', 'telegram-manager-woocommerce'); ?></small></div><label class="tcc-toggle" for="wctm-fast-dispatch"><input type="hidden" name="wctm_settings[fast_dispatch]" value="0"><input type="checkbox" id="wctm-fast-dispatch" name="wctm_settings[fast_dispatch]" value="1" <?php checked(!empty($settings['fast_dispatch'])); ?>><span class="slider"></span></label></div>
        </div>
    </section>

    <div class="tcc-save-bar">
        <?php submit_button(__('Save Settings', 'telegram-manager-woocommerce'), 'primary tcc-btn tcc-btn-primary', 'submit', false); ?>
        <span class="tcc-inline-status"><span class="dashicons dashicons-yes" aria-hidden="true"></span> <?php esc_html_e('Settings saved', 'telegram-manager-woocommerce'); ?></span>
    </div>
</form>

<?php include WCTM_PATH . 'views/layout-end.php'; ?>
