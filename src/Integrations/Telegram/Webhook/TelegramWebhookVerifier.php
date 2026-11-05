<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Webhook;

use WP_Error;
use WP_REST_Request;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class TelegramWebhookVerifier
{
    private const MAX_BODY_BYTES = 1048576;
    private const RATE_LIMIT = 120;
    private const RATE_WINDOW = 60;

    public function __construct(
        private BotOptionRepository $botOptions
    ) {
    }

    /**
     * Validate Telegram webhook secret header
     * `X-Telegram-Bot-Api-Secret-Token`.
     *
     * @param mixed $header
     * @return bool|WP_Error
     */
    public function verifyHeader(mixed $header): bool|WP_Error
    {
        $secret = trim((string) $this->botOptions->getWebhookSecret());
        $header = trim((string) $header);

        if ($secret === '') {
            return new WP_Error(
                'wctm_webhook_secret_missing',
                'Webhook secret is not configured.',
                ['status' => 500]
            );
        }

        if ($header === '') {
            return new WP_Error(
                'wctm_webhook_header_missing',
                'Webhook secret header is missing.',
                ['status' => 403]
            );
        }

        if (!hash_equals($secret, $header)) {
            return new WP_Error(
                'wctm_webhook_forbidden',
                'Invalid webhook secret.',
                ['status' => 403]
            );
        }

        return true;
    }

    public function verifyRequest(WP_REST_Request $request): bool|WP_Error
    {
        if (strtoupper($request->get_method()) !== 'POST') {
            return new WP_Error('wctm_webhook_method', 'Method not allowed.', ['status' => 405]);
        }

        $contentType = strtolower((string) $request->get_header('content-type'));
        if ($contentType === '' || strpos($contentType, 'application/json') !== 0) {
            return new WP_Error('wctm_webhook_content_type', 'JSON content type required.', ['status' => 415]);
        }

        if (strlen((string) $request->get_body()) > self::MAX_BODY_BYTES) {
            return new WP_Error('wctm_webhook_too_large', 'Webhook payload is too large.', ['status' => 413]);
        }

        $headerCheck = $this->verifyHeader($request->get_header('x-telegram-bot-api-secret-token'));
        if (is_wp_error($headerCheck)) {
            return $headerCheck;
        }

        $rateCheck = $this->checkRateLimit($request);
        if (is_wp_error($rateCheck)) {
            return $rateCheck;
        }

        $updateId = $this->updateId($request);
        if ($updateId === null) {
            return new WP_Error('wctm_webhook_update_id', 'A valid update_id is required.', ['status' => 400]);
        }

        return true;
    }

    public function isProcessed(WP_REST_Request $request): bool
    {
        $updateId = $this->updateId($request);
        return $updateId !== null && get_transient($this->replayKey($updateId)) !== false;
    }

    public function markProcessed(WP_REST_Request $request): void
    {
        $updateId = $this->updateId($request);
        if ($updateId !== null) {
            set_transient($this->replayKey($updateId), 1, DAY_IN_SECONDS);
        }
    }

    /** Atomically claim an update using WordPress' unique option_name index. */
    public function claimUpdate(WP_REST_Request $request): bool
    {
        $id = $this->updateId($request);
        if ($id === null || $this->isProcessed($request)) {
            return false;
        }
        $key = $this->claimKey($id);
        $claimedAt = (int) get_option($key, 0);
        if ($claimedAt > 0 && $claimedAt < time() - 600) {
            // A stale lock may be reclaimed after an interrupted request.
            delete_option($key);
        }
        return add_option($key, time(), '', false);
    }

    public function releaseUpdate(WP_REST_Request $request): void
    {
        $id = $this->updateId($request);
        if ($id !== null) {
            delete_option($this->claimKey($id));
        }
    }

    private function claimKey(int $id): string
    {
        return 'wctm_webhook_claim_' . hash('sha256', (string) $id);
    }

    private function checkRateLimit(WP_REST_Request $request): bool|WP_Error
    {
        $address = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'wctm_webhook_rate_' . substr(hash('sha256', $address), 0, 24);
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT) {
            return new WP_Error('wctm_webhook_rate_limit', 'Webhook rate limit exceeded.', ['status' => 429]);
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);
        return true;
    }

    private function updateId(WP_REST_Request $request): ?int
    {
        $data = $request->get_json_params();
        if (!is_array($data) || !isset($data['update_id']) || !is_numeric($data['update_id'])) {
            return null;
        }

        $id = (int) $data['update_id'];
        return $id >= 0 ? $id : null;
    }

    private function replayKey(int $updateId): string
    {
        return 'wctm_webhook_update_' . $updateId;
    }
}
