<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$settings = is_array($settings ?? null) ? $settings : [];
$adminBotUsername = (string) ($adminBotUsername ?? '');
$adminBotLink = (string) ($adminBotLink ?? '');
$adminBotSetup = is_array($adminBotSetup ?? null) ? $adminBotSetup : [];
$adminWebhookUrl = (string) ($adminWebhookUrl ?? '');
$adminChatId = (string) ($settings['admin_chat_id'] ?? $settings['chat_id'] ?? '');

$hasToken = !empty($adminBotSetup['hasToken']);
$hasUsername = !empty($adminBotSetup['hasUsername']);
$hasChatId = trim($adminChatId) !== '' || !empty($adminBotSetup['hasChatId']);
$stepsDone = (int) $hasToken + (int) $hasUsername + (int) $hasChatId;

$notifyNewOrder = array_key_exists('notify_new_order', $settings) ? !empty($settings['notify_new_order']) : true;
$notifyStatusChanged = array_key_exists('notify_status_changed', $settings) ? !empty($settings['notify_status_changed']) : true;
$notifyPaymentReceived = array_key_exists('notify_payment_received', $settings) ? !empty($settings['notify_payment_received']) : true;
$notifyRefundCreated = array_key_exists('notify_refund_created', $settings) ? !empty($settings['notify_refund_created']) : true;
?>

<section id="tab-admin-bot" class="wc-tg-tab wctm-ops-page">
    <style>
        .wctm-ops-page{--bg:#f6f7f7;--card:#fff;--border:#dfe7f3;--text:#111827;--muted:#64748b;--blue:#2481cc;--blue-dark:#1a6fb0;--soft:#f7fbff;--green:#059669;--orange:#d97706;--red:#dc2626;--radius:16px;color:var(--text)}
        .wctm-ops-page *{box-sizing:border-box}
        .wctm-ops-page-title{margin:0 0 18px;font-size:30px;line-height:1.15;color:var(--text);font-weight:800}
        .wctm-ops-topbar{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}
        .wctm-ops-kicker{margin:0 0 6px;color:var(--blue);font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}
        .wctm-ops-topbar p{margin:7px 0 0;color:var(--muted)}
        .wctm-ops-actions{display:flex;gap:10px;flex-wrap:wrap}
        .wctm-ops-btn,.wctm-ops-page .button{display:inline-flex!important;align-items:center;justify-content:center;gap:7px;min-height:38px!important;padding:8px 14px!important;border-radius:10px!important;font-weight:750!important;text-decoration:none!important;cursor:pointer;transition:.15s ease;box-shadow:none!important}
        .wctm-ops-page .button-secondary,.wctm-ops-btn{border:1px solid #c8d4e5!important;background:#fff!important;color:#1f2937!important}
        .wctm-ops-page .button-secondary:hover,.wctm-ops-btn:hover{border-color:#9bb7da!important;background:#f8fbff!important;color:#1f2937!important}
        .wctm-ops-page .button-primary,.wctm-ops-btn-primary{background:var(--blue)!important;border-color:var(--blue)!important;color:#fff!important}
        .wctm-ops-page .button-primary:hover,.wctm-ops-btn-primary:hover{background:var(--blue-dark)!important;border-color:var(--blue-dark)!important;color:#fff!important}
        .wctm-ops-btn-soft{background:#eef6ff!important;border-color:#cfe2ff!important;color:#1d4ed8!important}
        .wctm-ops-hero{display:grid;grid-template-columns:1fr 360px;gap:22px;align-items:center;padding:26px;margin-bottom:18px;border:1px solid #cfe2ff;border-radius:20px;background:linear-gradient(135deg,#fff 0%,#eef6ff 60%,#f8fbff 100%)}
        .wctm-ops-hero h3{margin:0 0 10px;font-size:25px;line-height:1.2;color:var(--text)}
        .wctm-ops-hero p{margin:0;color:#526174;line-height:1.6;max-width:760px}
        .wctm-ops-steps{display:grid;gap:10px}
        .wctm-ops-step-card{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1px solid var(--border);border-radius:14px;padding:12px}
        .wctm-ops-step-num{width:26px;height:26px;flex:0 0 26px;border-radius:999px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900}
        .wctm-ops-step-card strong{display:block}.wctm-ops-step-card span{display:block;margin-top:3px;color:var(--muted);font-size:12px;line-height:1.45}
        .wctm-ops-status-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px}
        .wctm-ops-status-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}
        .wctm-ops-status-label{font-size:12px;color:var(--muted);font-weight:900;text-transform:uppercase;letter-spacing:.03em}
        .wctm-ops-status-value{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:20px;font-weight:900}
        .wctm-ops-dot{width:10px;height:10px;border-radius:999px;background:var(--green)}.wctm-ops-dot.warn{background:var(--orange)}
        .wctm-ops-layout{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:18px;align-items:start}
        .wctm-ops-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);box-shadow:0 1px 2px rgba(15,23,42,.04);padding:20px;margin-bottom:16px}
        .wctm-ops-card-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:16px}
        .wctm-ops-card h3{margin:0;font-size:20px;color:var(--text)}
        .wctm-ops-desc{margin:5px 0 0;color:var(--muted);font-size:13px;line-height:1.5}
        .wctm-ops-section-step{display:inline-flex;align-items:center;justify-content:center;min-width:28px;height:28px;margin-right:8px;border-radius:999px;background:#eaf3ff;color:#226cd5;font-size:13px;font-weight:900}
        .wctm-ops-notice{padding:12px 14px;border:1px solid #cfe2ff;border-radius:12px;background:#f7fbff;color:#365575;font-size:13px;line-height:1.55;margin-bottom:14px}
        .wctm-ops-notice strong{color:var(--text)}.wctm-ops-notice a{color:#1d4ed8;font-weight:800;text-decoration:none}.wctm-ops-notice a:hover{text-decoration:underline}
        .wctm-ops-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.wctm-ops-full{grid-column:1/-1}
        .wctm-admin-bot-field{margin:0}.wctm-admin-bot-label{display:block;margin-bottom:7px;color:#334155;font-size:13px;font-weight:800}
        .wctm-admin-bot-input{width:100%;max-width:none!important;min-height:42px;border:1px solid #cfd8e3!important;border-radius:10px!important;background:#fff!important;color:#111827!important;padding:9px 11px!important;box-shadow:none!important}
        .wctm-admin-bot-input:focus{outline:none;border-color:var(--blue)!important;box-shadow:0 0 0 3px rgba(36,129,204,.12)!important}
        .wctm-admin-bot-help{margin:7px 0 0;color:var(--muted);font-size:12px;line-height:1.5}.wctm-admin-bot-help a{color:#1d4ed8;font-weight:800;text-decoration:none}
        .wctm-admin-bot-token-row,.wctm-admin-bot-chat-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:center}
        .wctm-admin-bot-token-input-wrap{display:grid;grid-template-columns:minmax(0,1fr) 44px;gap:8px;align-items:center}.wctm-admin-bot-icon-btn{min-width:44px!important;padding:0!important}
        .wctm-admin-bot-link-row{margin:8px 0 0;padding:10px 12px;border-radius:10px;background:#f8fbff;border:1px solid var(--border)}.wctm-admin-bot-link-row a{color:#1d4ed8;font-weight:800;text-decoration:none;word-break:break-all}
        .wctm-admin-bot-token-guide{margin-top:12px;padding:12px 14px;border:1px solid #cfe2ff;border-radius:12px;background:#f7fbff;color:#365575}.wctm-admin-bot-token-guide summary{cursor:pointer;font-weight:800;color:var(--text)}.wctm-admin-bot-token-steps{margin:10px 0 0;padding-left:20px}.wctm-admin-bot-token-steps li{margin:5px 0}.wctm-admin-bot-token-video{margin:10px 0 0}
        .wctm-inline-result{margin:10px 0 0!important;padding:10px 12px!important;border-radius:10px!important;font-size:13px!important;white-space:pre-line}.wctm-inline-result.is-success,.wctm-inline-result.success{background:#ecfdf5!important;color:#047857!important}.wctm-inline-result.is-error,.wctm-inline-result.error{background:#fef2f2!important;color:#b91c1c!important}
        .wctm-admin-bot-setup{padding:16px;border:1px solid #cfe2ff;border-radius:14px;background:#f7fbff;margin-bottom:16px}.wctm-admin-bot-setup-head{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:12px}.wctm-admin-bot-setup-summary{padding:5px 9px;border-radius:999px;background:#eef6ff;color:#1d4ed8;font-size:12px;font-weight:900}.wctm-admin-bot-setup-list{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:0;padding:0;list-style:none}.wctm-setup-item{display:flex;align-items:center;gap:8px;padding:10px;border:1px solid var(--border);border-radius:12px;background:#fff;color:var(--muted);font-weight:700}.wctm-setup-item.is-done{color:var(--green);border-color:#bbf7d0;background:#ecfdf5}.wctm-setup-dot{width:10px;height:10px;border-radius:999px;background:#cbd5e1}.wctm-setup-item.is-done .wctm-setup-dot{background:var(--green)}
        .wctm-alert-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.wctm-admin-bot-toggle-item{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px;border:1px solid var(--border);border-radius:14px;background:#fff}.wctm-admin-bot-toggle-left{display:flex;align-items:center;gap:10px}.wctm-admin-bot-mini-icon{width:26px;height:26px;display:flex;align-items:center;justify-content:center;border-radius:999px;font-size:12px;font-weight:900}.wctm-admin-bot-mini-icon.is-green{background:#ecfdf5;color:#047857}.wctm-admin-bot-mini-icon.is-gray{background:#f1f5f9;color:#64748b}.wctm-admin-bot-toggle-text{font-weight:800;color:#334155}
        .wctm-admin-bot-switch{position:relative;display:inline-flex;width:48px;height:26px;flex:0 0 48px}.wctm-admin-bot-switch input{opacity:0;width:0;height:0}.wctm-admin-bot-switch-slider{position:absolute;cursor:pointer;inset:0;background:#cbd5e1;border-radius:999px;transition:.15s}.wctm-admin-bot-switch-slider:before{content:'';position:absolute;width:20px;height:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.15s;box-shadow:0 1px 2px rgba(0,0,0,.18)}.wctm-admin-bot-switch input:checked+.wctm-admin-bot-switch-slider{background:var(--blue)}.wctm-admin-bot-switch input:checked+.wctm-admin-bot-switch-slider:before{transform:translateX(22px)}
        .wctm-admin-bot-webhook-row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;padding:16px;border:1px solid var(--border);border-radius:14px;background:#fff}.wctm-admin-bot-webhook-label{font-weight:900}.wctm-admin-bot-webhook-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.wc-tg-status-badge{display:inline-flex;align-items:center;gap:7px;min-height:38px;padding:7px 11px;border-radius:999px;font-size:12px;font-weight:900}.status-dot{width:9px;height:9px;border-radius:999px;background:currentColor}.status-pending{background:#fff7ed;color:#c2410c}.status-connected,.status-success,.status-active{background:#ecfdf5;color:#047857}.status-error{background:#fef2f2;color:#b91c1c}
        .wctm-ops-side{position:sticky;top:22px}.wctm-ops-side-card{border:1px solid var(--border);border-radius:var(--radius);padding:18px;background:#fff;margin-bottom:16px;box-shadow:0 1px 2px rgba(15,23,42,.04)}.wctm-ops-side-card h3{margin:0 0 8px;font-size:17px}.wctm-ops-side-card p{margin:0 0 14px;color:var(--muted);font-size:13px;line-height:1.5}.wctm-ops-checklist{display:flex;flex-direction:column;gap:10px}.wctm-ops-check{display:flex;gap:10px;padding:10px;border-radius:12px;background:#f8fbff;border:1px solid #e8f2ff;font-size:13px}.wctm-ops-check span{width:22px;height:22px;flex:0 0 22px;border-radius:999px;background:var(--blue);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:12px}.wctm-ops-links a{display:block;margin-bottom:8px;color:#1d4ed8;font-weight:800;text-decoration:none}
        .wctm-ops-savebar{position:sticky;bottom:0;z-index:20;margin-top:18px;padding:14px;border:1px solid var(--border);border-radius:16px;background:rgba(255,255,255,.92);backdrop-filter:blur(8px);box-shadow:0 -8px 24px rgba(15,23,42,.08);display:flex;justify-content:space-between;gap:12px;align-items:center}.wctm-ops-savebar p{margin:0;color:var(--muted);font-size:13px}
        @media(max-width:1120px){.wctm-ops-hero,.wctm-ops-layout{grid-template-columns:1fr}.wctm-ops-side{position:static}.wctm-ops-status-grid{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:782px){.wctm-ops-topbar,.wctm-admin-bot-token-row,.wctm-admin-bot-chat-row,.wctm-admin-bot-token-input-wrap,.wctm-admin-bot-webhook-row,.wctm-ops-savebar{display:flex;flex-direction:column;align-items:stretch}.wctm-ops-status-grid,.wctm-ops-form-grid,.wctm-admin-bot-setup-list,.wctm-alert-grid{grid-template-columns:1fr}.wctm-admin-bot-webhook-actions{justify-content:flex-start}}
    </style>

    <div class="wctm-ops-topbar">
        <div>
            <p class="wctm-ops-kicker"><?php echo esc_html__('WooCommerce Operations Bot', 'telegram-manager-woocommerce'); ?></p>
            <h2 class="wctm-ops-page-title"><?php echo esc_html__('Admin Bot Settings', 'telegram-manager-woocommerce'); ?></h2>
            <p><?php echo esc_html__('Connect a private Telegram bot for order alerts, payment confirmations, status changes and refunds.', 'telegram-manager-woocommerce'); ?></p>
        </div>
        <div class="wctm-ops-actions">
            <button type="button" class="button button-secondary" id="wctm-check-webhook"><?php echo esc_html__('Check webhook', 'telegram-manager-woocommerce'); ?></button>
            <button type="button" class="button button-primary" id="wctm-apply-admin-bot"><?php echo esc_html__('Save bot changes', 'telegram-manager-woocommerce'); ?></button>
        </div>
    </div>

    <div class="wctm-ops-hero">
        <div>
            <h3><?php echo esc_html__('Never miss a store event again', 'telegram-manager-woocommerce'); ?></h3>
            <p><?php echo esc_html__('This bot is for internal operations: new orders, paid orders, status changes, and refunds.', 'telegram-manager-woocommerce'); ?></p>
        </div>
        <div class="wctm-ops-steps">
            <div class="wctm-ops-step-card"><div class="wctm-ops-step-num">1</div><div><strong><?php echo esc_html__('Create bot', 'telegram-manager-woocommerce'); ?></strong><span><?php echo esc_html__('Use @BotFather and paste the token below.', 'telegram-manager-woocommerce'); ?></span></div></div>
            <div class="wctm-ops-step-card"><div class="wctm-ops-step-num">2</div><div><strong><?php echo esc_html__('Set alert chat', 'telegram-manager-woocommerce'); ?></strong><span><?php echo esc_html__('Send a message to the bot, then fetch your chat ID.', 'telegram-manager-woocommerce'); ?></span></div></div>
            <div class="wctm-ops-step-card"><div class="wctm-ops-step-num">3</div><div><strong><?php echo esc_html__('Choose alerts', 'telegram-manager-woocommerce'); ?></strong><span><?php echo esc_html__('Enable only the events your team needs.', 'telegram-manager-woocommerce'); ?></span></div></div>
        </div>
    </div>

    <div class="wctm-ops-status-grid">
        <div class="wctm-ops-status-card"><div class="wctm-ops-status-label"><?php echo esc_html__('Token', 'telegram-manager-woocommerce'); ?></div><div class="wctm-ops-status-value"><span class="wctm-ops-dot <?php echo $hasToken ? '' : 'warn'; ?>"></span><?php echo esc_html($hasToken ? __('Added', 'telegram-manager-woocommerce') : __('Missing', 'telegram-manager-woocommerce')); ?></div></div>
        <div class="wctm-ops-status-card"><div class="wctm-ops-status-label"><?php echo esc_html__('Username', 'telegram-manager-woocommerce'); ?></div><div class="wctm-ops-status-value"><span class="wctm-ops-dot <?php echo $hasUsername ? '' : 'warn'; ?>"></span><?php echo esc_html($hasUsername ? __('Ready', 'telegram-manager-woocommerce') : __('Check needed', 'telegram-manager-woocommerce')); ?></div></div>
        <div class="wctm-ops-status-card"><div class="wctm-ops-status-label"><?php echo esc_html__('Chat ID', 'telegram-manager-woocommerce'); ?></div><div class="wctm-ops-status-value"><span class="wctm-ops-dot <?php echo $hasChatId ? '' : 'warn'; ?>"></span><?php echo esc_html($hasChatId ? __('Set', 'telegram-manager-woocommerce') : __('Missing', 'telegram-manager-woocommerce')); ?></div></div>
        <div class="wctm-ops-status-card"><div class="wctm-ops-status-label"><?php echo esc_html__('Setup', 'telegram-manager-woocommerce'); ?></div><div class="wctm-ops-status-value"><span class="wctm-ops-dot"></span><?php echo esc_html(sprintf(__('%d/3', 'telegram-manager-woocommerce'), $stepsDone)); ?></div></div>
    </div>

    <div class="wctm-ops-layout">
        <main>
            <section class="wctm-ops-card">
                <div class="wctm-ops-card-head">
                    <div>
                        <h3><span class="wctm-ops-section-step">1</span><?php echo esc_html__('Connect operations bot', 'telegram-manager-woocommerce'); ?></h3>
                        <p class="wctm-ops-desc"><?php echo esc_html__('Use a dedicated private bot for admin and team alerts.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                    <button type="button" class="button button-primary" id="wctm-admin-bot-test"><?php echo esc_html__('Verify bot', 'telegram-manager-woocommerce'); ?></button>
                </div>

                <div class="wctm-ops-notice">
                    <strong><?php echo esc_html__('How to get a token:', 'telegram-manager-woocommerce'); ?></strong>
                    <?php echo esc_html__('open Telegram, find', 'telegram-manager-woocommerce'); ?>
                    <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>,
                    <?php echo esc_html__('run /newbot, create a bot name and username, then copy the token here.', 'telegram-manager-woocommerce'); ?>
                </div>

                <div class="wctm-ops-form-grid">
                    <div class="wctm-admin-bot-field wctm-ops-full">
                        <label class="wctm-admin-bot-label" for="bot-token"><?php echo esc_html__('Telegram bot token', 'telegram-manager-woocommerce'); ?></label>
                        <div class="wctm-admin-bot-token-row">
                            <div class="wctm-admin-bot-token-input-wrap">
                                <input type="password" id="bot-token" class="regular-text wctm-admin-bot-input" name="wctm_settings[bot_token]" value="<?php echo esc_attr((string) ($settings['bot_token'] ?? '')); ?>" autocomplete="off">
                                <button type="button" class="button button-secondary wctm-btn-toggle-token wctm-admin-bot-icon-btn" data-target="#bot-token" aria-label="<?php echo esc_attr__('Show or hide token', 'telegram-manager-woocommerce'); ?>">🔒</button>
                            </div>
                        </div>
                        <p class="wctm-admin-bot-help"><?php echo esc_html__('Paste the token, then click Verify bot.', 'telegram-manager-woocommerce'); ?></p>
                        <details class="wctm-admin-bot-token-guide">
                            <summary><?php echo esc_html__('How to create a Telegram bot', 'telegram-manager-woocommerce'); ?></summary>
                            <ol class="wctm-admin-bot-token-steps">
                                <li><?php echo esc_html__('Open @BotFather and press Start.', 'telegram-manager-woocommerce'); ?></li>
                                <li><?php echo esc_html__('Send /newbot and choose a bot name.', 'telegram-manager-woocommerce'); ?></li>
                                <li><?php echo esc_html__('Set a username ending with "bot".', 'telegram-manager-woocommerce'); ?></li>
                                <li><?php echo esc_html__('Copy the token and paste it above.', 'telegram-manager-woocommerce'); ?></li>
                            </ol>
                            <p class="wctm-admin-bot-token-video"><a href="https://www.youtube.com/results?search_query=BotFather+create+telegram+bot+token" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php echo esc_html__('Watch video guide', 'telegram-manager-woocommerce'); ?></a></p>
                        </details>
                        <div id="wctm-admin-bot-test-result" class="wctm-inline-result" style="display:none;" aria-live="polite"></div>
                    </div>
                </div>
            </section>

            <section class="wctm-ops-card">
                <div class="wctm-ops-card-head">
                    <div>
                        <h3><span class="wctm-ops-section-step">2</span><?php echo esc_html__('Set alert destination', 'telegram-manager-woocommerce'); ?></h3>
                        <p class="wctm-ops-desc"><?php echo esc_html__('Tell the plugin where order and payment alerts should be delivered.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>

                <div class="wctm-ops-form-grid">
                    <div class="wctm-admin-bot-field">
                        <label class="wctm-admin-bot-label" for="bot-username"><?php echo esc_html__('Bot username', 'telegram-manager-woocommerce'); ?></label>
                        <input type="text" id="bot-username" class="regular-text wctm-admin-bot-input" name="wctm_settings[bot_username]" value="<?php echo esc_attr($adminBotUsername); ?>">
                        <p id="bot-link-wrap" class="wctm-admin-bot-link-row" <?php echo $adminBotLink === '' ? 'style="display:none;"' : ''; ?>><a id="bot-link" href="<?php echo esc_url($adminBotLink); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($adminBotLink); ?></a></p>
                    </div>

                    <div class="wctm-admin-bot-field">
                        <label class="wctm-admin-bot-label" for="admin-chat-id"><?php echo esc_html__('Admin chat / channel ID', 'telegram-manager-woocommerce'); ?></label>
                        <div class="wctm-admin-bot-chat-row">
                            <input type="text" id="admin-chat-id" class="regular-text wctm-admin-bot-input" name="wctm_settings[admin_chat_id]" value="<?php echo esc_attr($adminChatId); ?>">
                            <button type="button" class="button button-secondary" id="wctm-get-chat-id"><?php echo esc_html__('Get Telegram ID', 'telegram-manager-woocommerce'); ?></button>
                        </div>
                        <p class="wctm-admin-bot-help"><?php echo esc_html__('Send any message to your bot in Telegram, then click Get Telegram ID.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>
                <div id="wctm-chat-id-result" class="wctm-inline-result" style="display:none;" aria-live="polite"></div>
            </section>

            <section class="wctm-ops-card">
                <div class="wctm-ops-card-head">
                    <div>
                        <h3><span class="wctm-ops-section-step">3</span><?php echo esc_html__('Choose operation alerts', 'telegram-manager-woocommerce'); ?></h3>
                        <p class="wctm-ops-desc"><?php echo esc_html__('Start with only the alerts your team actually needs.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>

                <div class="wctm-alert-grid">
                    <div class="wctm-admin-bot-toggle-item">
                        <div class="wctm-admin-bot-toggle-left"><span class="wctm-admin-bot-mini-icon <?php echo $notifyNewOrder ? 'is-green' : 'is-gray'; ?>"><?php echo $notifyNewOrder ? 'OK' : 'NO'; ?></span><label class="wctm-admin-bot-toggle-text" for="wctm-notify-new-order"><?php echo esc_html__('New order alert', 'telegram-manager-woocommerce'); ?></label></div>
                        <label class="wctm-admin-bot-switch"><input type="hidden" name="wctm_settings[notify_new_order]" value="0"><input type="checkbox" id="wctm-notify-new-order" name="wctm_settings[notify_new_order]" value="1" <?php checked($notifyNewOrder); ?>><span class="wctm-admin-bot-switch-slider"></span></label>
                    </div>
                    <div class="wctm-admin-bot-toggle-item">
                        <div class="wctm-admin-bot-toggle-left"><span class="wctm-admin-bot-mini-icon <?php echo $notifyStatusChanged ? 'is-green' : 'is-gray'; ?>"><?php echo $notifyStatusChanged ? 'OK' : 'NO'; ?></span><label class="wctm-admin-bot-toggle-text" for="wctm-notify-status-changed"><?php echo esc_html__('Order updates', 'telegram-manager-woocommerce'); ?></label></div>
                        <label class="wctm-admin-bot-switch"><input type="hidden" name="wctm_settings[notify_status_changed]" value="0"><input type="checkbox" id="wctm-notify-status-changed" name="wctm_settings[notify_status_changed]" value="1" <?php checked($notifyStatusChanged); ?>><span class="wctm-admin-bot-switch-slider"></span></label>
                    </div>
                    <div class="wctm-admin-bot-toggle-item">
                        <div class="wctm-admin-bot-toggle-left"><span class="wctm-admin-bot-mini-icon <?php echo $notifyPaymentReceived ? 'is-green' : 'is-gray'; ?>"><?php echo $notifyPaymentReceived ? 'OK' : 'NO'; ?></span><label class="wctm-admin-bot-toggle-text" for="wctm-notify-payment-received"><?php echo esc_html__('Payment confirmation', 'telegram-manager-woocommerce'); ?></label></div>
                        <label class="wctm-admin-bot-switch"><input type="hidden" name="wctm_settings[notify_payment_received]" value="0"><input type="checkbox" id="wctm-notify-payment-received" name="wctm_settings[notify_payment_received]" value="1" <?php checked($notifyPaymentReceived); ?>><span class="wctm-admin-bot-switch-slider"></span></label>
                    </div>
                    <div class="wctm-admin-bot-toggle-item">
                        <div class="wctm-admin-bot-toggle-left"><span class="wctm-admin-bot-mini-icon <?php echo $notifyRefundCreated ? 'is-green' : 'is-gray'; ?>"><?php echo $notifyRefundCreated ? 'OK' : 'NO'; ?></span><label class="wctm-admin-bot-toggle-text" for="wctm-notify-refund-created"><?php echo esc_html__('Refund alerts', 'telegram-manager-woocommerce'); ?></label></div>
                        <label class="wctm-admin-bot-switch"><input type="hidden" name="wctm_settings[notify_refund_created]" value="0"><input type="checkbox" id="wctm-notify-refund-created" name="wctm_settings[notify_refund_created]" value="1" <?php checked($notifyRefundCreated); ?>><span class="wctm-admin-bot-switch-slider"></span></label>
                    </div>
                </div>
            </section>

            <section class="wctm-ops-card">
                <div class="wctm-ops-card-head">
                    <div>
                        <h3><span class="wctm-ops-section-step">4</span><?php echo esc_html__('Telegram Control Center — Pro', 'telegram-manager-woocommerce'); ?></h3>
                        <p class="wctm-ops-desc"><?php echo esc_html__('Commands, daily reports, revenue reports, low stock alerts, customer cards and message action buttons.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>
                <div class="wctm-alert-grid">
                    <?php
                    $proFlags = [
                        'admin_bot_commands' => __('Admin bot commands', 'telegram-manager-woocommerce'),
                        'daily_reports' => __('Daily reports', 'telegram-manager-woocommerce'),
                        'revenue_reports' => __('Revenue reports', 'telegram-manager-woocommerce'),
                        'low_stock_alerts' => __('Low stock alerts', 'telegram-manager-woocommerce'),
                        'telegram_crm_cards' => __('CRM cards in Telegram', 'telegram-manager-woocommerce'),
                        'message_action_buttons' => __('Message action buttons', 'telegram-manager-woocommerce'),
                    ];
                    foreach ($proFlags as $flagKey => $flagLabel) :
                        $enabled = !empty($settings[$flagKey]);
                        ?>
                        <div class="wctm-admin-bot-toggle-item">
                            <div class="wctm-admin-bot-toggle-left"><span class="wctm-admin-bot-mini-icon <?php echo $enabled ? 'is-green' : 'is-gray'; ?>"><?php echo $enabled ? 'PRO' : 'OFF'; ?></span><label class="wctm-admin-bot-toggle-text" for="wctm-<?php echo esc_attr($flagKey); ?>"><?php echo esc_html($flagLabel); ?></label></div>
                            <label class="wctm-admin-bot-switch"><input type="hidden" name="wctm_settings[<?php echo esc_attr($flagKey); ?>]" value="0"><input type="checkbox" id="wctm-<?php echo esc_attr($flagKey); ?>" name="wctm_settings[<?php echo esc_attr($flagKey); ?>]" value="1" <?php checked($enabled); ?>><span class="wctm-admin-bot-switch-slider"></span></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="wctm-ops-form-grid" style="margin-top:14px;">
                    <div class="wctm-admin-bot-field">
                        <label class="wctm-admin-bot-label" for="daily-report-time"><?php echo esc_html__('Daily report time', 'telegram-manager-woocommerce'); ?></label>
                        <input type="time" id="daily-report-time" class="regular-text wctm-admin-bot-input" name="wctm_settings[daily_report_time]" value="<?php echo esc_attr((string) ($settings['daily_report_time'] ?? '09:00')); ?>">
                    </div>
                    <div class="wctm-admin-bot-field">
                        <label class="wctm-admin-bot-label" for="low-stock-threshold"><?php echo esc_html__('Low stock threshold', 'telegram-manager-woocommerce'); ?></label>
                        <input type="number" min="0" id="low-stock-threshold" class="regular-text wctm-admin-bot-input" name="wctm_settings[low_stock_threshold]" value="<?php echo esc_attr((string) ($settings['low_stock_threshold'] ?? 5)); ?>">
                    </div>
                </div>
            </section>

            <section class="wctm-ops-card">
                <div class="wctm-ops-card-head">
                    <div>
                        <h3><span class="wctm-ops-section-step">5</span><?php echo esc_html__('Delivery / webhook', 'telegram-manager-woocommerce'); ?></h3>
                        <p class="wctm-ops-desc"><?php echo esc_html__('Webhook is required for Telegram to deliver updates to your store endpoint.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                </div>

                <div class="wctm-admin-bot-webhook-row">
                    <div>
                        <div class="wctm-admin-bot-webhook-label"><?php echo esc_html__('Telegram delivery status', 'telegram-manager-woocommerce'); ?></div>
                        <p class="wctm-admin-bot-help"><?php echo esc_html__('Check webhook after saving the token. If it mismatches, click reconnect.', 'telegram-manager-woocommerce'); ?></p>
                    </div>
                    <div class="wctm-admin-bot-webhook-actions">
                        <span id="wctm-webhook-status-badge" class="wc-tg-status-badge status-pending"><span class="status-dot"></span><span id="wctm-webhook-status-text"><?php echo esc_html__('Not checked yet', 'telegram-manager-woocommerce'); ?></span></span>
                        <button type="button" class="button button-secondary" id="wctm-refresh-webhook"><?php echo esc_html__('Reconnect delivery', 'telegram-manager-woocommerce'); ?></button>
                        <button type="button" class="button button-secondary" id="wctm-check-webhook"><?php echo esc_html__('Check webhook', 'telegram-manager-woocommerce'); ?></button>
                    </div>
                </div>
                <div id="wctm-webhook-result" class="wctm-inline-result" style="display:none;" aria-live="polite"></div>

                <div class="wctm-admin-bot-field" style="margin-top:14px;">
                    <label class="wctm-admin-bot-label" for="admin-webhook-url"><?php echo esc_html__('Admin Bot webhook URL', 'telegram-manager-woocommerce'); ?></label>
                    <input type="text" id="admin-webhook-url" class="regular-text wctm-admin-bot-input" value="<?php echo esc_attr($adminWebhookUrl); ?>" readonly>
                </div>
            </section>

            <div class="wctm-ops-savebar">
                <p><strong><?php echo esc_html__('Ready?', 'telegram-manager-woocommerce'); ?></strong> <?php echo esc_html__('Save settings, verify bot, then send a test message.', 'telegram-manager-woocommerce'); ?></p>
                <div class="wctm-ops-actions">
                    <button type="button" class="button button-secondary" id="wctm-admin-bot-test"><?php echo esc_html__('Send test alert', 'telegram-manager-woocommerce'); ?></button>
                    <button type="button" class="button button-primary" id="wctm-apply-admin-bot"><?php echo esc_html__('Save bot changes', 'telegram-manager-woocommerce'); ?></button>
                </div>
            </div>
        </main>

        <aside class="wctm-ops-side">
            <section class="wctm-ops-side-card">
                <h3><?php echo esc_html__('Setup checklist', 'telegram-manager-woocommerce'); ?></h3>
                <p><?php echo esc_html__('Use this bot only for internal operations alerts.', 'telegram-manager-woocommerce'); ?></p>
                <div class="wctm-ops-checklist">
                    <div class="wctm-ops-check"><span>1</span><div><strong><?php echo esc_html__('Create bot in @BotFather', 'telegram-manager-woocommerce'); ?></strong><br><?php echo esc_html__('Copy token and username.', 'telegram-manager-woocommerce'); ?></div></div>
                    <div class="wctm-ops-check"><span>2</span><div><strong><?php echo esc_html__('Send message to bot', 'telegram-manager-woocommerce'); ?></strong><br><?php echo esc_html__('This allows fetching the chat ID.', 'telegram-manager-woocommerce'); ?></div></div>
                    <div class="wctm-ops-check"><span>3</span><div><strong><?php echo esc_html__('Check webhook', 'telegram-manager-woocommerce'); ?></strong><br><?php echo esc_html__('Confirms Telegram points to your store endpoint.', 'telegram-manager-woocommerce'); ?></div></div>
                </div>
            </section>

            <section class="wctm-ops-side-card">
                <h3><?php echo esc_html__('Helpful links', 'telegram-manager-woocommerce'); ?></h3>
                <div class="wctm-ops-links">
                    <a href="https://t.me/BotFather" target="_blank" rel="noopener noreferrer">@BotFather</a>
                    <a href="https://core.telegram.org/bots/features#botfather" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('BotFather documentation', 'telegram-manager-woocommerce'); ?></a>
                    <a href="https://core.telegram.org/bots/webhooks" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Telegram webhooks', 'telegram-manager-woocommerce'); ?></a>
                </div>
            </section>
        </aside>
    </div>
</section>
