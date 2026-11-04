<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot\Sales;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleTodayCommandHandler
{
    public function handle(): array
    {
        return [
            'message' => 'Today command is routed by AdminBotWebhookHandler.',
            'keyboard' => [],
        ];
    }
}
