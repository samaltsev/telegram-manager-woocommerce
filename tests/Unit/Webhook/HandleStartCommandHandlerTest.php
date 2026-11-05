<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCTM\AdminBot\AdminBotSubscriberRepositoryInterface;
use WCTM\Application\Handler\Command\AdminBot\HandleStartCommandHandler;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/AdminBot/AdminBotSubscriberRepositoryInterface.php';
require_once dirname(__DIR__, 2) . '/src/Application/Handler/Command/AdminBot/HandleStartCommandHandler.php';

final class HandleStartCommandHandlerTest extends TestCase
{
    public function testHandleReturnsWelcomePayload(): void
    {
        $repo = $this->createMock(AdminBotSubscriberRepositoryInterface::class);
        $repo->expects($this->once())->method('addSubscriber');
        $repo->expects($this->once())->method('updateStatusByChatId')->with('100', 'subscribed');

        $handler = new HandleStartCommandHandler($repo);
        $result = $handler->handle('100', 'john');

        $this->assertSame('paused', $result['pref_key']);
        $this->assertFalse($result['pref_value']);
        $this->assertNotEmpty($result['keyboard']);
    }
}
