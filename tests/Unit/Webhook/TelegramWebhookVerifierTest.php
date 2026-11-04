<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCTM\Integrations\Telegram\Webhook\TelegramWebhookVerifier;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(private string $code, private string $message, private array $data = []) {}
        public function get_error_code(){ return $this->code; }
        public function get_error_message(){ return $this->message; }
    }
}

final class TelegramWebhookVerifierTest extends TestCase
{
    public function testVerifyHeaderSuccess(): void
    {
        $repo = $this->createMock(BotOptionRepository::class);
        $repo->method('getWebhookSecret')->willReturn('secret-token');

        $verifier = new TelegramWebhookVerifier($repo);
        $result = $verifier->verifyHeader('secret-token');

        $this->assertTrue($result);
    }

    public function testVerifyHeaderFailsOnInvalidSecret(): void
    {
        $repo = $this->createMock(BotOptionRepository::class);
        $repo->method('getWebhookSecret')->willReturn('secret-token');

        $verifier = new TelegramWebhookVerifier($repo);
        $result = $verifier->verifyHeader('invalid');

        $this->assertInstanceOf(WP_Error::class, $result);
    }
}
