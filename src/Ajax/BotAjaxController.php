<?php
declare(strict_types=1);

namespace WCTM\Ajax;

use Throwable;
use WCTM\Integrations\Telegram\Client\TelegramClient;
use WCTM\Integrations\Telegram\Debug\TelegramConnectionVerifyer;
use WCTM\Common\MessageSender;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;
use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Telegram\UpdatesHelper;
use WCTM\Telegram\WebhookManager;

if (!defined('ABSPATH')) {
    exit;
}

class BotAjaxController extends AbstractAjaxController
{
    protected TelegramClient $client;
    protected MessageSender $sender;
    protected TelegramConnectionVerifyer $tester;
    protected BotOptionRepository $botOptions;

    public function __construct(
        BotSettingsRepository $botSettings,
        TelegramClient $client,
        MessageSender $sender,
        TelegramConnectionVerifyer $tester,
        BotOptionRepository $botOptions
    ) {
        parent::__construct($botSettings);
        $this->client = $client;
        $this->sender = $sender;
        $this->tester = $tester;
        $this->botOptions = $botOptions;
    }

    public function save_bot_settings(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash((string) $_POST['token'])) : '';
        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash((string) $_POST['username'])) : '';
        $botName = isset($_POST['bot_name']) ? sanitize_text_field(wp_unslash((string) $_POST['bot_name'])) : '';
        $chatId = isset($_POST['chat_id']) ? wp_unslash((string) $_POST['chat_id']) : '';
        $chatId = (string) preg_replace('/[^0-9\-]/', '', $chatId);
        $notificationKeys = ['notify_new_order', 'notify_status_changed', 'notify_payment_received', 'notify_refund_created'];

        $this->botSettings->update([
            'bot_token' => $token,
            'bot_name' => $botName,
            'bot_username' => ltrim($username, '@'),
            'admin_chat_id' => $chatId,
        ]);
        update_option('chat_id', $chatId, false);

        $settings = (array) get_option(BotSettingsRepository::OPTION_KEY, []);
        foreach ($notificationKeys as $key) {
            if (array_key_exists($key, $_POST)) {
                $settings[$key] = !empty($_POST[$key]) ? 1 : 0;
            }
        }
        update_option(BotSettingsRepository::OPTION_KEY, $settings);

        wp_send_json_success([
            'message' => 'Telegram bot settings saved.',
        ]);
    }

    public function verify_bot(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = isset($_POST['token']) ? trim(sanitize_text_field(wp_unslash((string) $_POST['token']))) : '';

        if ($token === '') {
            wp_send_json_error([
                'message' => __('Bot token is required.', 'telegram-manager-woocommerce'),
            ]);
        }

        try {
            $me = $this->tester->getMe($token);
        } catch (Throwable $e) {
            wp_send_json_error([
                'message' => 'Telegram connection failed: ' . $e->getMessage(),
            ], 502);
        }

        if (is_wp_error($me)) {
            wp_send_json_error([
                'message' => $me->get_error_message(),
            ], 502);
        }

        $result = is_array($me['result'] ?? null) ? $me['result'] : [];
        if (empty($result['id'])) {
            wp_send_json_error([
                'message' => __('Telegram did not return bot information. Check the token and try again.', 'telegram-manager-woocommerce'),
            ], 502);
        }

        $username = ltrim(sanitize_text_field((string) ($result['username'] ?? '')), '@');
        $name = sanitize_text_field((string) ($result['first_name'] ?? $result['name'] ?? ''));
        $link = $username !== '' ? 'https://t.me/' . rawurlencode($username) : '';

        $this->botSettings->update([
            'bot_token' => $token,
            'bot_name' => $name,
            'bot_username' => $username,
        ]);

        wp_send_json_success([
            'message' => __('Bot verified successfully.', 'telegram-manager-woocommerce'),
            'bot_name' => $name,
            'username' => $username,
            'link' => $link,
            'bot_type' => $botType,
        ]);
    }

    public function test_connection(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = '';

        if (!empty($_POST['token'])) {
            $token = trim(sanitize_text_field(wp_unslash((string) $_POST['token'])));
        }

        if ($token === '') {
            $token = trim($this->botOptions->getTokenForType($botType));
        }

        if ($token === '') {
            wp_send_json_error([
                'message' => $this->settingsUsernameLabel($botType) . ': bot token is not set.',
            ]);
        }

        try {
            $me = $this->tester->getMe($token);
        } catch (Throwable $e) {
            wp_send_json_error([
                'message' => 'Telegram connection failed: ' . $e->getMessage(),
            ], 502);
        }

        $chatId = !empty($_POST['chat_id'])
            ? trim((string) preg_replace('/[^0-9\-]/', '', wp_unslash((string) $_POST['chat_id'])))
            : trim($this->botOptions->getChatIdForType($botType));
        $result = is_array($me['result'] ?? null) ? $me['result'] : [];
        $username = (string) ($result['username'] ?? '');
        $botName = (string) ($result['first_name'] ?? $result['name'] ?? '');
        if ($username !== '' || $chatId !== '' || $botName !== '') {
            $this->botSettings->update([
                'bot_name' => $botName,
                'bot_username' => ltrim($username, '@'),
                'admin_chat_id' => $chatId,
            ]);
        }

        if ($chatId === '') {
            wp_send_json_success([
                'message' => $this->settingsUsernameLabel($botType)
                    . ': bot found @' . $username
                    . '. Set chat ID to send a test message.',
                'bot_name' => $botName,
                'username' => $username,
                'link' => $username !== '' ? 'https://t.me/' . rawurlencode(ltrim($username, '@')) : '',
                'chat_id' => null,
            ]);
        }

        $text = "✅ <b>Telegram Control Center for WooCommerce</b>\n\n"
            . "Bot connection is active.\n"
            . "Site: " . wp_parse_url(home_url('/'), PHP_URL_HOST);

        $this->sender->send($botType, [['telegram_user_id' => $chatId]], $text, [
            'bot_type' => $botType,
        ]);

        update_option('wctm_last_test_message', current_time('mysql'), false);

        wp_send_json_success([
            'message' => 'Telegram is ready',
            'bot_name' => $botName,
            'username' => $username,
            'link' => $username !== '' ? 'https://t.me/' . rawurlencode(ltrim($username, '@')) : '',
            'chat_id' => $chatId,
        ]);
    }

    public function refresh_webhook(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = !empty($_POST['token'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['token'])))
            : $this->botOptions->getTokenForType($botType);
        $webhookUrl = WebhookManager::adminWebhookUrl();

        if ($token === '') {
            wp_send_json_error([
                'message' => $this->settingsUsernameLabel($botType) . ': bot token is not set.',
            ]);
        }

        if (WebhookManager::isLocalEnvironment()) {
            $result = $this->client->deleteWebhook($token);

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                ]);
            }

            wp_send_json_success([
                'message' => $this->settingsUsernameLabel($botType) . ': local environment detected, webhook disabled, polling mode enabled.',
                'url' => $webhookUrl,
                'mode' => 'polling',
            ]);
        }

        $result = $this->client->setWebhook(
            $token,
            $webhookUrl,
            $this->botOptions->getWebhookSecret()
        );

        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        wp_send_json_success([
            'message' => $this->settingsUsernameLabel($botType) . ': delivery endpoint updated.',
            'url' => $webhookUrl,
            'mode' => 'webhook',
        ]);
    }

    public function check_webhook(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = trim($this->botOptions->getTokenForType($botType));

        if ($token === '') {
            wp_send_json_error([
                'message' => $this->settingsUsernameLabel($botType) . ': bot token is not set.',
            ]);
        }

        $expectedUrl = WebhookManager::adminWebhookUrl();

        if (WebhookManager::isLocalEnvironment()) {
            wp_send_json_success([
                'message' => $this->settingsUsernameLabel($botType) . ': local environment detected, polling mode is active.',
                'mode' => 'polling',
                'expected_url' => $expectedUrl,
                'current_url' => '',
                'is_configured' => true,
                'pending_update_count' => 0,
                'last_error_message' => '',
            ]);
        }

        try {
            $info = $this->client->getWebhookInfo($token);
        } catch (Throwable $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
            ]);
        }

        $result = is_array($info['result'] ?? null) ? $info['result'] : [];
        $currentUrl = (string) ($result['url'] ?? '');
        $isConfigured = $currentUrl !== '' && untrailingslashit($currentUrl) === untrailingslashit($expectedUrl);

        wp_send_json_success([
            'message' => $isConfigured
                ? $this->settingsUsernameLabel($botType) . ': webhook is configured correctly.'
                : $this->settingsUsernameLabel($botType) . ': webhook URL does not match expected endpoint.',
            'mode' => 'webhook',
            'expected_url' => $expectedUrl,
            'current_url' => $currentUrl,
            'is_configured' => $isConfigured,
            'pending_update_count' => (int) ($result['pending_update_count'] ?? 0),
            'last_error_message' => (string) ($result['last_error_message'] ?? ''),
            'last_error_date' => isset($result['last_error_date']) ? (string) $result['last_error_date'] : '',
        ]);
    }

    public function get_chat_id(): void
    {
        $this->guard();

        $botType = $this->botType();
        $token = !empty($_POST['token'])
            ? trim(sanitize_text_field(wp_unslash((string) $_POST['token'])))
            : $this->botOptions->getTokenForType($botType);

        if ($token === '') {
            wp_send_json_error([
                'message' => $this->settingsUsernameLabel($botType) . ': bot token is not set.',
            ]);
        }

        try {
            $this->client->deleteWebhook($token);
        } catch (Throwable $e) {
            // Free setup uses getUpdates for Chat ID discovery; continue even if
            // an old webhook cannot be removed at this exact moment.
        }

        $result = $this->tester->getUpdates($token);
        if (is_wp_error($result)) {
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        $targets = UpdatesHelper::extractChatTargets($result);
        update_option('wctm_last_updates_snapshot', $targets, false);
        $first = UpdatesHelper::preferredChatId($targets);

        wp_send_json_success([
            'message' => $first
                ? $this->settingsUsernameLabel($botType) . ': chat ID found.'
                : $this->settingsUsernameLabel($botType) . ': no new chat IDs found.',
            'chat_id' => $first,
            'targets' => $targets,
        ]);
    }

    public function run_diagnostics(): void
    {
        $this->guard();

        $checks = [];
        $hasErrors = false;

        foreach (['admin' => 'Telegram Bot'] as $type => $label) {
            $token = trim($this->botOptions->getTokenForType($type));
            $chatId = trim($this->botOptions->getChatIdForType($type));

            if ($token === '') {
                $checks[] = [
                    'key' => $type . '_token',
                    'label' => $label . ' token',
                    'status' => 'error',
                    'message' => 'Missing token',
                ];
                $hasErrors = true;
            } else {
                $me = $this->tester->getMe($token);
                if (is_wp_error($me)) {
                    $checks[] = [
                        'key' => $type . '_api',
                        'label' => $label . ' API',
                        'status' => 'error',
                        'message' => $me->get_error_message(),
                    ];
                    $hasErrors = true;
                } else {
                    $username = (string) (($me['result']['username'] ?? ''));
                    $checks[] = [
                        'key' => $type . '_api',
                        'label' => $label . ' API',
                        'status' => 'ok',
                        'message' => $username !== '' ? ('Connected as @' . $username) : 'Connected',
                    ];
                }
            }

            $checks[] = [
                'key' => $type . '_chat_id',
                'label' => $label . ' chat_id',
                'status' => $chatId !== '' ? 'ok' : 'warn',
                'message' => $chatId !== '' ? $chatId : 'Not configured',
            ];
        }

        $cronOff = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $checks[] = [
            'key' => 'cron',
            'label' => 'WP-Cron',
            'status' => $cronOff ? 'warn' : 'ok',
            'message' => $cronOff ? 'Off (DISABLE_WP_CRON=true)' : 'Active',
        ];

        $checks[] = [
            'key' => 'webhook_mode',
            'label' => 'Webhook mode',
            'status' => WebhookManager::isLocalEnvironment() ? 'warn' : 'ok',
            'message' => WebhookManager::isLocalEnvironment() ? 'Local polling mode' : 'Webhook mode',
        ];

        wp_send_json_success([
            'message' => $hasErrors ? 'Diagnostics completed with errors.' : 'Diagnostics completed successfully.',
            'has_errors' => $hasErrors,
            'checks' => $checks,
            'run_at' => current_time('mysql'),
        ]);
    }
}
