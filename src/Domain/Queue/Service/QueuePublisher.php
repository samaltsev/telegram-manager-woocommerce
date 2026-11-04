<?php
declare(strict_types=1);
namespace WCTM\Domain\Queue\Service;

use WCTM\Domain\Queue\DTO\SendTelegramMessageJobData;
use WCTM\Domain\Queue\Repository\QueueRepositoryInterface;
use WCTM\Infrastructure\Queue\JobSerializer;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;
use WCTM\Logger\Logger;
use WCTM\Queue\QueueScheduler;
use WCTM\Telegram\ChatTarget;

if (!defined('ABSPATH')) {
    exit;
}

class QueuePublisher
{
    protected QueueRepositoryInterface $repo;
    protected JobSerializer $serializer;
    protected QueueSettingsRepository $queueSettings;

    public function __construct(QueueRepositoryInterface $repo, JobSerializer $serializer, QueueSettingsRepository $queueSettings)
    {
        $this->repo = $repo;
        $this->serializer = $serializer;
        $this->queueSettings = $queueSettings;
    }

    public function publishTelegramForTargets($event, array $payload, array $targets, $orderId = null)
    {
        $maxAttempts = max(1, absint($this->queueSettings->get('max_attempts', 3)));
        $count = 0;
        $traceId = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('wctm_', true);

        foreach ($targets as $index => $target) {
            if (!$target instanceof ChatTarget || $target->chatId === '') {
                continue;
            }

            $extra = isset($payload['extra']) && is_array($payload['extra']) ? $payload['extra'] : array();
            $botType = isset($extra['bot_type']) ? sanitize_key((string) $extra['bot_type']) : 'admin';
            $text = isset($payload['text']) ? (string) $payload['text'] : '';

            $dto = new SendTelegramMessageJobData(
                $botType,
                (string) $target->chatId,
                $text,
                $extra,
                $target->threadId,
                $orderId,
                $event
            );

            $normalized = $this->serializer->normalizePayload('send_telegram_message', $dto);

            /**
             * Production idempotency.
             *
             * The key must be stable for the same logical notification. Do not add
             * microtime/random values here, otherwise duplicate protection becomes
             * impossible and WooCommerce hook re-runs can create multiple messages.
             */
            $idempotencyKey = $this->idempotencyKey(
                (string) $event,
                $orderId,
                $target,
                $text,
                $extra
            );

            $this->repo->push(
                'send_telegram_message',
                $normalized,
                $idempotencyKey,
                $traceId,
                10,
                $maxAttempts,
                null
            );
            $count++;
        }

        Logger::log('info', 'queue', 'Queued jobs: ' . $count, array(
            'event' => $event,
            'order_id' => $orderId,
            'trace_id' => $traceId,
        ));

        QueueScheduler::scheduleSingle();

        if ($count > 0 && (int) $this->queueSettings->get('fast_dispatch', 1) === 1) {
            $this->dispatchImmediately();
        }

        return $count;
    }

    private function dispatchImmediately(): void
    {
        if (!has_action('wctm_process_queue_single')) {
            return;
        }

        try {
            do_action('wctm_process_queue_single');
        } catch (\Throwable $e) {
            Logger::log('error', 'queue', 'Immediate queue dispatch failed; cron fallback remains scheduled', array(
                'error' => $e->getMessage(),
            ));
        }
    }

    /**
     * Runtime bot replies and connection tests must be repeatable. Keep stable
     * idempotency only for WooCommerce event notifications where duplicates are risky.
     *
     * @param array<string, mixed> $extra
     */
    private function idempotencyKey(string $event, $orderId, ChatTarget $target, string $text, array $extra): ?string
    {
        $event = sanitize_key($event);

        if (in_array($event, ['admin_bot_message', 'free_admin_bot_reply'], true)) {
            return null;
        }

        $idempotencyParts = array(
            'wctm',
            $event,
            (string) absint($orderId),
            (string) $target->chatId,
            (string) ($target->threadId ?? ''),
            substr(hash('sha256', $text), 0, 24),
        );

        if (isset($extra['reply_markup'])) {
            $idempotencyParts[] = substr(hash('sha256', wp_json_encode($extra['reply_markup'])), 0, 24);
        }

        return hash('sha256', implode('|', $idempotencyParts));
    }
}
