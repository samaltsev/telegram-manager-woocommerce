<?php
declare(strict_types=1);

namespace WCTM\Infrastructure\Queue;

use WCTM\Domain\Queue\Repository\QueueRepositoryInterface;
use WCTM\Logger\Logger;
use WCTM\Queue\RetryHandler;

if (!defined('ABSPATH')) {
    exit;
}

final class QueueWorker
{
    public function __construct(
        private QueueRepositoryInterface $repo,
        private $handler,
        private RetryHandler $retryHandler
    ) {
    }

    public function runOnce(int $maxJobs = 10): void
    {
        $jobs = $this->repo->reserveNextBatch('worker-' . wp_generate_uuid4(), $maxJobs, 60);
        foreach ($jobs as $job) {
            $jobId = method_exists($job, 'id') ? (int) $job->id() : 0;
            $attempt = method_exists($job, 'attempts') ? (int) $job->attempts() : 0;
            $maxAttempts = method_exists($job, 'maxAttempts') ? (int) $job->maxAttempts() : 3;
            try {
                ($this->handler)($job);
                if ($jobId > 0) {
                    $this->repo->markDone($jobId);
                }
            } catch (\Throwable $e) {
                $delay = $this->retryHandler->nextDelay($e, $attempt + 1);
                $retry = $this->retryHandler->shouldRetry($e, $attempt + 1, $maxAttempts);
                if ($jobId > 0) {
                    if ($retry) {
                        $this->repo->markRetry($jobId, time() + $delay, 'queue_handler_exception', $e->getMessage());
                    } else {
                        $this->repo->markFailed($jobId, 'queue_handler_exception', $e->getMessage());
                    }
                }
                Logger::log('error', 'queue', 'Job processing failed', [
                    'job_id' => $jobId ?: null,
                    'error' => $e->getMessage(),
                    'will_retry' => $retry,
                ]);
            }
        }
    }
}
