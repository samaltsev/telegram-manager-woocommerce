<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot\Sales;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleOrdersCommandHandler
{
    public function handle(): array
    {
        return [
            'message' => 'Orders command is routed by AdminBotWebhookHandler.',
            'keyboard' => [],
        ];
    }
}
