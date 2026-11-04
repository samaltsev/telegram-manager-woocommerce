<?php
declare(strict_types=1);
namespace WCTM\Queue;

use WCTM\Domain\Queue\Service\QueuePublisher;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @deprecated 4.4.3-p2 Use QueuePublisher directly in new code.
 */
class MessageQueue
{
    protected QueuePublisher $publisher;

    public function __construct(QueuePublisher $publisher)
    {
        $this->publisher = $publisher;
    }

    public function enqueueForTargets($event, array $payload, array $targets, $orderId = null)
    {
        return $this->publisher->publishTelegramForTargets($event, $payload, $targets, $orderId);
    }
}
