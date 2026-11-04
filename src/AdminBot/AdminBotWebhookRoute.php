<?php
declare(strict_types=1);

namespace WCTM\AdminBot;

use Throwable;
use WP_Error;
use WP_REST_Request;
use WCTM\Integrations\Telegram\Webhook\TelegramWebhookVerifier;
use WCTM\Logger\Logger;
use WCTM\Security\SecurityPolicy;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminBotWebhookRoute
{
    public function __construct(
        private TelegramWebhookVerifier $verifier,
        private AdminBotWebhookHandler $handler
    ) {
    }

    public function register(): void
    {
        register_rest_route('wctm/v1', '/admin-webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleWebhook'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(WP_REST_Request $request): bool|WP_Error
    {
        return SecurityPolicy::verifyTelegramWebhookRequest($this->verifier, $request);
    }

    public function handleWebhook(WP_REST_Request $request): array|WP_Error
    {
        if ($this->verifier->isProcessed($request)) {
            return ['ok' => true, 'duplicate' => true];
        }

        $data = $request->get_json_params();

        try {
            $this->handler->handle(is_array($data) ? $data : []);
            $this->verifier->markProcessed($request);
            update_option('wctm_last_webhook_hit', current_time('mysql'), false);

            return ['ok' => true];
        } catch (Throwable $e) {
            Logger::log('error', 'webhook', 'AdminBot webhook error', [
                'message' => $e->getMessage(),
            ]);

            return new WP_Error(
                'wctm_admin_webhook_error',
                'Failed to process admin webhook.',
                ['status' => 500]
            );
        }
    }
}
