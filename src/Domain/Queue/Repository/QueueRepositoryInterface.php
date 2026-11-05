<?php
declare(strict_types=1);
namespace WCTM\Domain\Queue\Repository;

if (!defined('ABSPATH')) {
    exit;
}

interface QueueRepositoryInterface
{
    public function push($jobType, array $payload, $idempotencyKey = null, $traceId = null, $priority = 10, $maxAttempts = 3, $availableAt = null);
    public function reserveNextBatch($workerId, $limit = 10, $staleAfterSeconds = 600);
    public function markDone($id);
    public function markRetry($id, $nextAttemptAtUnix, $errorCode, $errorMessage);
    public function markFailed($id, $errorCode, $errorMessage);
    public function storeAttempt($jobId, $attemptNumber, $status, $errorCode = null, $errorMessage = null);
}
