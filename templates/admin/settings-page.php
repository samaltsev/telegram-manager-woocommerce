<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$hasToken = trim((string) ($settings['bot_token'] ?? '')) !== '';
$hasChatId = trim((string) $adminChatId) !== '';
$lastTest = (string) get_option('wctm_last_test_message', '');
$botUsername = trim((string) ($adminBotUsername ?? ''));
$botName = $botUsername !== '' ? '@' . ltrim($botUsername, '@') : __('Telegram bot not verified yet', 'telegram-manager-woocommerce');
$botImage = defined('WCTM_URL') ? WCTM_URL . 'assets/img/bot.png' : '';

$notifyNewOrder = !empty($settings['notify_new_order']);
$notifyStatus = !empty($settings['notify_status_changed']);
$notifyRefund = !empty($settings['notify_refund_created']);
?>
<div class="wrap wctm-admin wctm-modern-page wctm-connection-page">
    <form method="post" action="options.php" id="wctm-settings-form">
        <?php settings_fields('wctm_settings_group'); ?>

        <div class="wctm-dash-header">
            <div class="wctm-dash-brand">
                <?php if ($botImage !== '') : ?>
                    <img class="wctm-dash-logo" src="<?php echo esc_url($botImage); ?>" alt="" />
                <?php endif; ?>
                <div>
                    <h1><?php esc_html_e('Connection & Notifications', 'telegram-manager-woocommerce'); ?> <span class="wctm-free-badge"><?php esc_html_e('Free', 'telegram-manager-woocommerce'); ?></span></h1>
                    <p><?php esc_html_e('Set up your Telegram bot and choose which WooCommerce events should be sent.', 'telegram-manager-woocommerce'); ?></p>
                </div>
            </div>
            <div class="wctm-dash-actions">
                <span class="wctm-health-pill <?php echo esc_attr($hasToken && $hasChatId ? 'is-ok' : 'is-warn'); ?>">
                    <?php echo esc_html($hasToken && $hasChatId ? __('Configuration saved', 'telegram-manager-woocommerce') : __('Setup required', 'telegram-manager-woocommerce')); ?>
                </span>
            </div>
        </div>

        <ol class="wctm-modern-stepper">
            <li class="<?php echo esc_attr($hasToken ? 'is-done' : 'is-active'); ?>" data-step="token">
                <span><?php echo esc_html($hasToken ? '✓' : '1'); ?></span>
                <strong><?php esc_html_e('Bot Token', 'telegram-manager-woocommerce'); ?></strong>
                <small><?php esc_html_e('Connect your bot', 'telegram-manager-woocommerce'); ?></small>
            </li>
            <li class="<?php echo esc_attr($hasChatId ? 'is-done' : (!$hasToken ? '' : 'is-active')); ?>" data-step="chat_id">
                <span><?php echo esc_html($hasChatId ? '✓' : '2'); ?></span>
                <strong><?php esc_html_e('Telegram Chat ID', 'telegram-manager-woocommerce'); ?></strong>
                <small><?php esc_html_e('Send to this chat', 'telegram-manager-woocommerce'); ?></small>
            </li>
            <li class="<?php echo esc_attr($lastTest !== '' ? 'is-done' : ($hasToken && $hasChatId ? 'is-active' : '')); ?>" data-step="test_message">
                <span><?php echo esc_html($lastTest !== '' ? '✓' : '3'); ?></span>
                <strong><?php esc_html_e('Test Message', 'telegram-manager-woocommerce'); ?></strong>
                <small><?php esc_html_e('Verify connection', 'telegram-manager-woocommerce'); ?></small>
            </li>
        </ol>

        <div class="wctm-settings-layout">
            <section class="wctm-modern-card">
                <div class="wctm-card-title-row">
                    <span class="wctm-card-icon">🤖</span>
                    <div>
                        <h2><?php esc_html_e('Telegram Bot Setup', 'telegram-manager-woocommerce'); ?></h2>
                        <p><?php esc_html_e('Configure your Telegram bot connection.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>

                <div class="wctm-modern-field">
                    <label for="bot-token"><?php esc_html_e('Bot Token', 'telegram-manager-woocommerce'); ?></label>
                    <div class="wctm-token-field">
                        <input type="password" id="bot-token" name="wctm_settings[bot_token]" value="<?php echo esc_attr((string) ($settings['bot_token'] ?? '')); ?>" autocomplete="off">
                        <button type="button" class="wctm-icon-button" id="wctm-toggle-token" aria-label="<?php esc_attr_e('Show or hide token', 'telegram-manager-woocommerce'); ?>">👁</button>
                    </div>
                    <small><?php esc_html_e('Get your token from @BotFather on Telegram.', 'telegram-manager-woocommerce'); ?></small>
                </div>

                <input type="hidden" id="bot-username" name="wctm_settings[bot_username]" value="<?php echo esc_attr($adminBotUsername); ?>">

                <div class="wctm-bot-info-panel <?php echo esc_attr($hasToken ? 'is-connected' : 'is-muted'); ?>">
                    <div>
                        <span><?php esc_html_e('Bot Name', 'telegram-manager-woocommerce'); ?></span>
                        <strong><?php echo esc_html($botName); ?></strong>
                    </div>
                    <div>
                        <span><?php esc_html_e('Username', 'telegram-manager-woocommerce'); ?></span>
                        <strong><?php echo esc_html($botUsername !== '' ? '@' . ltrim($botUsername, '@') : '—'); ?></strong>
                    </div>
                    <em><?php echo esc_html($hasToken ? __('Connected', 'telegram-manager-woocommerce') : __('Not verified yet', 'telegram-manager-woocommerce')); ?></em>
                </div>

                <div class="wctm-modern-field">
                    <label for="telegram-chat-id"><?php esc_html_e('Telegram Chat ID', 'telegram-manager-woocommerce'); ?></label>
                    <div class="wctm-chat-row">
                        <input type="text" id="telegram-chat-id" name="wctm_settings[admin_chat_id]" value="<?php echo esc_attr($adminChatId); ?>">
                        <button type="button" class="wctm-btn wctm-btn-secondary" id="wctm-get-chat-id"><?php esc_html_e('Get Chat ID', 'telegram-manager-woocommerce'); ?></button>
                    </div>
                    <small><?php esc_html_e('Send any message to the bot first, then click Get Chat ID.', 'telegram-manager-woocommerce'); ?></small>
                    <div id="wctm-chat-id-result" class="wctm-inline-result" style="display:none;"></div>
                    <?php if ($hasChatId) : ?>
                        <span class="wctm-mini-badge is-ok"><?php echo esc_html(sprintf(__('Connected to chat: %s', 'telegram-manager-woocommerce'), $adminChatId)); ?></span>
                    <?php endif; ?>
                </div>

                <div class="wctm-test-panel">
                    <div>
                        <h3><?php esc_html_e('Test Connection', 'telegram-manager-woocommerce'); ?></h3>
                        <p><?php esc_html_e('Send a test message to make sure everything is working.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                    <button type="button" class="wctm-btn wctm-btn-telegram" id="wctm-admin-bot-test"><?php esc_html_e('Send Test Message', 'telegram-manager-woocommerce'); ?></button>
                    <div id="wctm-admin-bot-test-result" class="wctm-inline-result" style="display:none;"></div>
                    <?php if ($lastTest !== '') : ?>
                        <div class="wctm-test-success">
                            <span>✓</span>
                            <div>
                                <strong><?php esc_html_e('Test message delivered', 'telegram-manager-woocommerce'); ?></strong>
                                <small><?php echo esc_html(sprintf(__('Last test: %s', 'telegram-manager-woocommerce'), $lastTest)); ?></small>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="wctm-modern-card">
                <div class="wctm-card-title-row">
                    <span class="wctm-card-icon">🔔</span>
                    <div>
                        <h2><?php esc_html_e('Choose Notifications', 'telegram-manager-woocommerce'); ?></h2>
                        <p><?php esc_html_e('Select which WooCommerce events should be sent to Telegram.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>

                <?php
                $notificationCards = [
                    [
                        'key' => 'notify_new_order',
                        'enabled' => $notifyNewOrder,
                        'icon' => '🛒',
                        'title' => __('New Order', 'telegram-manager-woocommerce'),
                        'description' => __('Receive Telegram notification when customer creates order.', 'telegram-manager-woocommerce'),
                        'preview_id' => 'wctm-preview-new-order',
                        'preview' => "🛒 New Order #4312\nCustomer: John Smith\nTotal: €120\nPayment: Stripe\nStatus: Processing",
                    ],
                    [
                        'key' => 'notify_status_changed',
                        'enabled' => $notifyStatus,
                        'icon' => '🔄',
                        'title' => __('Order Status Changed', 'telegram-manager-woocommerce'),
                        'description' => __('Receive Telegram notification when order status updates.', 'telegram-manager-woocommerce'),
                        'preview_id' => 'wctm-preview-status',
                        'preview' => "🔄 Order #4312 status changed\nPrevious: Processing\nNew: Completed\nCustomer: John Smith",
                    ],
                    [
                        'key' => 'notify_refund_created',
                        'enabled' => $notifyRefund,
                        'icon' => '↩',
                        'title' => __('Refund Created', 'telegram-manager-woocommerce'),
                        'description' => __('Receive Telegram notification when a refund is created.', 'telegram-manager-woocommerce'),
                        'preview_id' => 'wctm-preview-refund',
                        'preview' => "↩ Refund created\nOrder: #4312\nRefund amount: €20\nReason: Customer request",
                    ],
                ];
                ?>

                <div class="wctm-notification-list">
                    <?php foreach ($notificationCards as $card) : ?>
                        <article class="wctm-notification-card <?php echo esc_attr($card['enabled'] ? 'is-enabled' : 'is-disabled'); ?>">
                            <span class="wctm-notification-icon"><?php echo esc_html($card['icon']); ?></span>
                            <div class="wctm-notification-body">
                                <div class="wctm-notification-head">
                                    <div>
                                        <h3><?php echo esc_html($card['title']); ?></h3>
                                        <p><?php echo esc_html($card['description']); ?></p>
                                    </div>
                                    <label class="wctm-switch">
                                        <input type="hidden" name="wctm_settings[<?php echo esc_attr($card['key']); ?>]" value="0">
                                        <input class="wctm-notification-toggle" type="checkbox" name="wctm_settings[<?php echo esc_attr($card['key']); ?>]" value="1" <?php checked($card['enabled']); ?>>
                                        <span></span>
                                    </label>
                                </div>
                                <button type="button" class="wctm-preview-toggle" data-wctm-preview="<?php echo esc_attr($card['preview_id']); ?>"><?php esc_html_e('Preview', 'telegram-manager-woocommerce'); ?> ▼</button>
                                <div id="<?php echo esc_attr($card['preview_id']); ?>" class="wctm-telegram-message-preview" hidden>
                                    <div class="wctm-tg-preview-head">
                                        <span><?php esc_html_e('Telegram Shop Bot', 'telegram-manager-woocommerce'); ?></span>
                                        <small><?php esc_html_e('Today 14:28', 'telegram-manager-woocommerce'); ?></small>
                                    </div>
                                    <pre><?php echo esc_html($card['preview']); ?></pre>
                                    <em>✓✓ <?php esc_html_e('Delivered', 'telegram-manager-woocommerce'); ?></em>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>

        <section class="wctm-modern-card wctm-queue-settings-card">
            <div class="wctm-card-title-row">
                <span class="wctm-card-icon">⚡</span>
                <div>
                    <h2><?php esc_html_e('Queue Settings', 'telegram-manager-woocommerce'); ?></h2>
                    <p><?php esc_html_e('Configure how the delivery queue works.', 'telegram-manager-woocommerce'); ?></p>
                </div>
            </div>

            <div class="wctm-queue-settings-grid">
                <label class="wctm-modern-field">
                    <span><?php esc_html_e('Retry attempts', 'telegram-manager-woocommerce'); ?></span>
                    <input type="number" min="1" max="10" id="wctm-max-attempts" name="wctm_settings[max_attempts]" value="<?php echo esc_attr((string) ($settings['max_attempts'] ?? 3)); ?>">
                    <small><?php esc_html_e('How many times to retry failed jobs.', 'telegram-manager-woocommerce'); ?></small>
                </label>

                <label class="wctm-modern-field">
                    <span><?php esc_html_e('Retry delay', 'telegram-manager-woocommerce'); ?></span>
                    <input type="number" min="30" id="wctm-retry-delay" name="wctm_settings[retry_delay]" value="<?php echo esc_attr((string) ($settings['retry_delay'] ?? 300)); ?>">
                    <small><?php esc_html_e('Delay between retry attempts in seconds.', 'telegram-manager-woocommerce'); ?></small>
                </label>

                <label class="wctm-immediate-card">
                    <input type="hidden" name="wctm_settings[fast_dispatch]" value="0">
                    <span class="wctm-switch">
                        <input type="checkbox" id="wctm-fast-dispatch" name="wctm_settings[fast_dispatch]" value="1" <?php checked(!empty($settings['fast_dispatch'])); ?>>
                        <span></span>
                    </span>
                    <strong><?php esc_html_e('Immediate delivery', 'telegram-manager-woocommerce'); ?></strong>
                    <small><?php esc_html_e('Send immediately after adding to the queue when possible.', 'telegram-manager-woocommerce'); ?></small>
                </label>
            </div>
        </section>

        <div class="wctm-save-bar">
            <?php submit_button(__('Save Settings', 'telegram-manager-woocommerce'), 'primary wctm-btn wctm-btn-primary', 'submit', false); ?>
            <button type="button" class="wctm-btn wctm-btn-secondary" id="wctm-apply-admin-bot"><?php esc_html_e('Save bot only', 'telegram-manager-woocommerce'); ?></button>
            <span class="wctm-inline-saved">✓ <?php esc_html_e('Settings saved', 'telegram-manager-woocommerce'); ?></span>
        </div>
    </form>
</div>
