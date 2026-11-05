<?php
declare(strict_types=1);

namespace WCTM\Security;

use WP_Error;
use WCTM\Integrations\Telegram\Webhook\TelegramWebhookVerifier;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class SecurityPolicy
{
    private const AJAX_NONCE_ACTION = 'wctm_admin_nonce';
    private const AJAX_NONCE_FIELD = 'nonce';

    public static function enforceAdminAjax(): void
    {
        check_ajax_referer(self::AJAX_NONCE_ACTION, self::AJAX_NONCE_FIELD);

        if (current_user_can('manage_woocommerce') || current_user_can('manage_options')) {
            return;
        }

        wp_send_json_error(
            [
                'message' => esc_html__('Access denied.', 'telegram-manager-woocommerce'),
            ],
            403
        );
    }

    public static function enforceBotAdministration(): void
    {
        check_ajax_referer(self::AJAX_NONCE_ACTION, self::AJAX_NONCE_FIELD);
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Administrator access required.', 'telegram-manager-woocommerce')], 403);
        }
    }

    public static function verifyTelegramWebhookRequest(
        TelegramWebhookVerifier $verifier,
        WP_REST_Request $request
    ): bool|WP_Error
    {
        return $verifier->verifyRequest($request);
    }

    public static function telegramSecretHeader(): string
    {
        return isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'])
            ? trim((string) wp_unslash($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']))
            : '';
    }
}
