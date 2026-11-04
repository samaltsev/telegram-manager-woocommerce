<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\Webhook;

use WCTM\AdminBot\Service\OrderActionService;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleAdminOrderActionCallbackHandler
{
    public function __construct(private OrderActionService $orders) {}

    public function handle(string $callbackData): bool
    {
        $parts = explode(':', $callbackData);
        $orderId = isset($parts[1]) ? (int) $parts[1] : 0;
        $action = isset($parts[2]) ? sanitize_key((string) $parts[2]) : '';

        return $this->orders->changeStatus($orderId, $action, 'Changed from Telegram Control Center.');
    }
}
