<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot\Sales;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleCustomersCommandHandler
{
    public function handle(): array
    {
        return [
            'message' => 'Customers command is routed by AdminBotWebhookHandler.',
            'keyboard' => [],
        ];
    }
}
