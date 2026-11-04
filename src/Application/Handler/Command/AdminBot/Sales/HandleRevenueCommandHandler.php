<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot\Sales;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleRevenueCommandHandler
{
    public function handle(): array
    {
        return [
            'message' => 'Revenue command is routed by AdminBotWebhookHandler.',
            'keyboard' => [],
        ];
    }
}
