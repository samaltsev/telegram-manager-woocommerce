<?php
declare(strict_types=1);

namespace WCTM\Queue;

use WCTM\Logger\LoggerInterface;

class ActionSchedulerService
{
    private const GROUP = 'wctm_telegram_notifications';
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Ставит уведомление в очередь
     */
    public function schedule_notification(int $order_id, string $event_type): void
    {
        $hook = 'wctm/process_telegram_notification';
        $args = [$order_id, $event_type];

        // Проверка на дубликаты (идемпотентность)
        if (as_has_scheduled_action($hook, $args, self::GROUP)) {
            return;
        }

        // Асинхронное выполнение
        as_schedule_single_action(time(), $hook, $args, self::GROUP);
        
        $this->logger->info("Scheduled Telegram notification for order #{$order_id} (Event: {$event_type})");
    }

    /**
     * Обработчик задачи из очереди
     */
    public function process_notification(int $order_id, string $event_type): void
    {
        try {
            // Здесь вызываем твой существующий класс отправки уведомлений
            // $notifier = new NotificationSender($order_id, $event_type);
            // $notifier->send();
            
            $this->logger->info("Successfully processed notification for order #{$order_id}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to process notification for order #{$order_id}: " . $e->getMessage());
            throw $e; // Action Scheduler сделает retry автоматически
        }
    }

    /**
     * Регистрация хуков Action Scheduler
     */
    public function register_hooks(): void
    {
        add_action('wctm/process_telegram_notification', [$this, 'process_notification'], 10, 2);
    }
}