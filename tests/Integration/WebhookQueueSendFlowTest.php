<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WCTM\Domain\Queue\Entity\QueueJob;
use WCTM\Domain\Queue\Repository\QueueRepositoryInterface;
use WCTM\Infrastructure\Queue\QueueWorker;
use WCTM\Integrations\WordPress\Option\QueueSettingsRepository;
use WCTM\Queue\RetryHandler;

require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/src/Domain/Queue/Repository/QueueRepositoryInterface.php';
require_once dirname(__DIR__, 2) . '/src/Domain/Queue/Entity/QueueJob.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Queue/QueueWorker.php';

if (!function_exists('absint')) {
    function absint($value): int { return abs((int) $value); }
}
if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4(): string { return 'test-uuid-4'; }
}

final class WebhookQueueSendFlowTest extends TestCase
{
    public function testWebhookToQueueToSendFlow(): void
    {
        $repo = new InMemoryQueueRepository();
        $sent = [];

        $repo->push('send_telegram_message', [
            'event' => 'admin_notification',
            'chat_id' => '12345',
            'text' => 'Hello from webhook',
        ]);

        $settings = $this->createMock(QueueSettingsRepository::class);
        $settings->method('get')->willReturnCallback(static function (string $key, $default = null) {
            return match ($key) {
                'retry_delay' => 5,
                'retry_max_delay' => 60,
                default => $default,
            };
        });

        $worker = new QueueWorker(
            $repo,
            static function (QueueJob $job) use (&$sent): void {
                $payload = $job->payload();
                $sent[] = [
                    'chat_id' => (string) ($payload['chat_id'] ?? ''),
                    'text' => (string) ($payload['text'] ?? ''),
                ];
            },
            new RetryHandler($settings)
        );

        $worker->runOnce(10);

        $this->assertCount(1, $sent);
        $this->assertSame('12345', $sent[0]['chat_id']);
        $this->assertSame('Hello from webhook', $sent[0]['text']);
        $this->assertSame('done', $repo->statusOf(1));
    }
}

final class InMemoryQueueRepository implements QueueRepositoryInterface
{
    /** @var array<int, array<string, mixed>> */
    private array $jobs = [];

    public function push($jobType, array $payload, $idempotencyKey = null, $traceId = null, $priority = 10, $maxAttempts = 3, $availableAt = null)
    {
        $id = count($this->jobs) + 1;
        $this->jobs[$id] = [
            'id' => $id,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => (int) $maxAttempts,
        ];

        return $id;
    }

    public function reserveNextBatch($workerId, $limit = 10, $staleAfterSeconds = 600)
    {
        $batch = [];
        foreach ($this->jobs as $id => $job) {
            if ($job['status'] !== 'pending') {
                continue;
            }
            $this->jobs[$id]['status'] = 'processing';
            $batch[] = new QueueJob($job);
            if (count($batch) >= $limit) {
                break;
            }
        }

        return $batch;
    }

    public function markDone($id)
    {
        $this->jobs[(int) $id]['status'] = 'done';
        return 1;
    }

    public function markRetry($id, $nextAttemptAtUnix, $errorCode, $errorMessage)
    {
        $this->jobs[(int) $id]['status'] = 'retry';
        $this->jobs[(int) $id]['attempts']++;
        return 1;
    }

    public function markFailed($id, $errorCode, $errorMessage)
    {
        $this->jobs[(int) $id]['status'] = 'failed';
        $this->jobs[(int) $id]['attempts']++;
        return 1;
    }

    public function storeAttempt($jobId, $attemptNumber, $status, $errorCode = null, $errorMessage = null)
    {
        return 1;
    }

    public function add(array $job)
    {
        return $this->push('send_telegram_message', $job);
    }

    public function claimBatch($limit = 10)
    {
        return $this->reserveNextBatch('legacy-worker', $limit, 600);
    }

    public function reschedule($id, $attempts, $error, $delay)
    {
        return $this->markRetry($id, time() + (int) $delay, 'retry', (string) $error);
    }

    public function statusOf(int $id): string
    {
        return (string) ($this->jobs[$id]['status'] ?? '');
    }
}
