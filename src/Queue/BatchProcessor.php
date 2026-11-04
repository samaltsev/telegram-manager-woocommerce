<?php
declare(strict_types=1);

namespace WCTM\Queue;

use WCTM\Logger\LoggerInterface;

class BatchProcessor
{
    private const BATCH_SIZE = 50;
    private const GROUP = 'wctm_telegram_batches';
    private LoggerInterface $logger;
    private ActionSchedulerService $scheduler;

    public function __construct(LoggerInterface $logger, ActionSchedulerService $scheduler)
    {
        $this->logger = $logger;
        $this->scheduler = $scheduler;
    }

    /**
     * Разбивает массив ID заказов на чанки и ставит их в очередь
     */
    public function schedule_batch_notifications(array $order_ids, string $event_type): void
    {
        if (empty($order_ids)) {
            return;
        }

        $chunks = array_chunk($order_ids, self::BATCH_SIZE);
        $total_chunks = count($chunks);

        foreach ($chunks as $index => $chunk) {
            $hook = 'wctm/process_telegram_notification_batch';
            $args = [$chunk, $event_type];

            // Задержка между чанками для защиты от Rate Limit Telegram API
            $delay = time() + ($index * 2);

            as_schedule_single_action($delay, $hook, $args, self::GROUP);
        }

        $this->logger->info("Scheduled batch notifications: " . count($order_ids) . " orders in {$total_chunks} chunks.");
    }

    /**
     * Обработчик пакетной задачи
     */
    public function process_batch(array $order_ids_chunk, string $event_type): void
    {
        $this->logger->info("Processing batch chunk of " . count($order_ids_chunk) . " orders.");

        foreach ($order_ids_chunk as $order_id) {
            $this->scheduler->process_notification((int) $order_id, $event_type);
            
            // Пауза для защиты от Rate Limit Telegram API (30 msg/sec)
            usleep(50000); // 0.05 секунды
        }
    }

    /**
     * Регистрация хуков
     */
    public function register_hooks(): void
    {
        add_action('wctm/process_telegram_notification_batch', [$this, 'process_batch'], 10, 2);
    }
}