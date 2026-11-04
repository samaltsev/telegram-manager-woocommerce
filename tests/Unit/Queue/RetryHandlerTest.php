<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCTM\Queue\RetryHandler;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;

if (!function_exists('absint')) { function absint($value){ return abs((int) $value); } }
if (!function_exists('str_contains')) { function str_contains(string $haystack,string $needle): bool { return $needle === '' || strpos($haystack,$needle)!==false; } }

final class RetryHandlerTest extends TestCase
{
    public function testNextDelayUsesExponentialBackoff(): void
    {
        $settings = $this->createMock(QueueSettingsRepository::class);
        $settings->method('get')->willReturnCallback(static function (string $key, $default = null) {
            if ($key === 'retry_delay') { return 10; }
            if ($key === 'retry_max_delay') { return 300; }
            return $default;
        });

        $handler = new RetryHandler($settings);
        $delay = $handler->nextDelay(new RuntimeException('timeout'), 3);

        $this->assertGreaterThanOrEqual(20, $delay);
        $this->assertLessThanOrEqual(60, $delay);
    }
}
