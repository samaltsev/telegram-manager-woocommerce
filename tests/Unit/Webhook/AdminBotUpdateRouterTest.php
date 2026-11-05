<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCTM\Application\UseCase\AdminBot\AdminBotUpdateRouter;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Application/UseCase/AdminBot/AdminBotUpdateRouter.php';

final class AdminBotUpdateRouterTest extends TestCase
{
    public function testRoutesMessage(): void
    {
        $router = new AdminBotUpdateRouter();
        $result = $router->route([
            'message' => [
                'chat' => ['id' => '777'],
                'from' => ['username' => 'john'],
                'text' => ' /start ',
            ],
        ]);

        $this->assertSame('message', $result['type']);
        $this->assertSame('777', $result['chat_id']);
        $this->assertSame('/start', $result['text']);
    }
}
