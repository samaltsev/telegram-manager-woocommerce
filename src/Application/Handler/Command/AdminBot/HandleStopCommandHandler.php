<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot;

use WCTM\AdminBot\AdminBotSubscriberRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleStopCommandHandler
{
    public function __construct(
        private AdminBotSubscriberRepositoryInterface $subscribers
    ) {
    }

    /** @return array{pref_key:string,pref_value:bool,message:string} */
    public function handle(string $chatId): array
    {
        $this->subscribers->updateStatusByChatId($chatId, 'paused');

        return [
            'pref_key' => 'paused',
            'pref_value' => true,
            'message' => "🔕 Admin alerts paused\n\nYou will not receive new order and support notifications.\n\nUse /start to enable alerts again.",
        ];
    }
}
