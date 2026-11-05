<?php
declare(strict_types=1);

namespace WCTM\Infrastructure\Persistence\Repository;

use WCTM\Domain\Queue\Entity\QueueJob;
use WCTM\Domain\Queue\Repository\QueueRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

final class DbQueueRepository implements QueueRepositoryInterface
{
    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wctm_jobs';
    }

    public function attemptsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'wctm_job_attempts';
    }

    /**
     * @param array<string, mixed> $payload
     * @param string|int|null $idempotencyKey
     * @param string|null $traceId
     * @param string|int|null $availableAt
     */
    public function push(
        $jobType,
        array $payload,
        $idempotencyKey = null,
        $traceId = null,
        $priority = 10,
        $maxAttempts = 3,
        $availableAt = null
    ): int {
        global $wpdb;

        $table = $this->tableName();
        $now = current_time('mysql');
        $available = $this->normalizeDateTime($availableAt) ?? $now;

        $chatId = isset($payload['chat_id'])
            ? sanitize_text_field((string) $payload['chat_id'])
            : '';

        $threadId = isset($payload['message_thread_id']) &&
            $payload['message_thread_id'] !== '' &&
            $payload['message_thread_id'] !== null
            ? absint($payload['message_thread_id'])
            : null;

        $orderId = !empty($payload['order_id']) ? absint($payload['order_id']) : null;
        $event = sanitize_key(isset($payload['event']) ? (string) $payload['event'] : (string) $jobType);

        if ($idempotencyKey) {
            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT id
                 FROM {$table}
                 WHERE idempotency_key = %s
                   AND status IN ('pending','processing','retry')
                 LIMIT 1",
                (string) $idempotencyKey
            ));

            if ($existingId) {
                return (int) $existingId;
            }
        }

        $data = [
            'job_type' => sanitize_key((string) $jobType),
            'event' => $event,
            'order_id' => $orderId,
            'chat_id' => $chatId,
            'message_thread_id' => $threadId,
            'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'priority' => (int) $priority,
            'attempts' => 0,
            'max_attempts' => max(1, absint($maxAttempts)),
            'idempotency_key' => $idempotencyKey ? sanitize_text_field((string) $idempotencyKey) : null,
            'trace_id' => $traceId ? sanitize_text_field((string) $traceId) : null,
            'worker_id' => null,
            'available_at' => $available,
            'reserved_at' => null,
            'locked_at' => null,
            'last_error_code' => null,
            'last_error_message' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $result = $wpdb->insert($table, $data);

        if ($result === false && $wpdb->last_error && strpos($wpdb->last_error, 'Duplicate entry') !== false) {
            if ($idempotencyKey) {
                $existingId = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$table} WHERE idempotency_key = %s LIMIT 1",
                    (string) $idempotencyKey
                ));

                return $existingId ? (int) $existingId : 0;
            }

            return 0;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<int, QueueJob>
     */
    public function reserveNextBatch($workerId, $limit = 10, $staleAfterSeconds = 600): array
    {
        global $wpdb;

        $table = $this->tableName();
        $now = current_time('mysql');
        $staleMysql = wp_date('Y-m-d H:i:s', current_time('timestamp') - max(60, (int) $staleAfterSeconds));
        $limit = max(1, absint($limit));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE (
                (status IN ('pending','retry') AND available_at <= %s)
                OR
                (status = 'processing'
                    AND (
                        (reserved_at IS NOT NULL AND reserved_at < %s)
                        OR
                        (locked_at IS NOT NULL AND locked_at < %s)
                    )
                )
             )
             ORDER BY priority ASC, id ASC
             LIMIT %d",
            $now,
            $staleMysql,
            $staleMysql,
            $limit
        ), ARRAY_A);

        $jobs = [];

        foreach ((array) $rows as $row) {
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'processing',
                    'worker_id' => sanitize_text_field((string) $workerId),
                    'reserved_at' => $now,
                    'locked_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => (int) $row['id'],
                    'status' => $row['status'],
                    // Compare-and-swap: a reclaimed processing job must still have
                    // the same lease timestamp as when it was selected.
                    'reserved_at' => $row['reserved_at'],
                    'locked_at' => $row['locked_at'],
                ],
                ['%s', '%s', '%s', '%s', '%s'],
                ['%d', '%s', '%s', '%s']
            );

            if (!$updated) {
                continue;
            }

            $fresh = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $row['id']),
                ARRAY_A
            );

            if (!$fresh) {
                continue;
            }

            $fresh['payload'] = json_decode((string) $fresh['payload'], true);
            if (!is_array($fresh['payload'])) {
                $fresh['payload'] = [];
            }

            $jobs[] = new QueueJob($fresh);
        }

        return $jobs;
    }

    /**
     * Complete a task only if this worker still owns its lease.  The
     * conditional write prevents a timed-out worker overwriting a newer lease.
     */
    public function markDoneOwned(int $id, string $workerId): int
    {
        global $wpdb;
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()} SET status = 'done', reserved_at = NULL,
             locked_at = NULL, worker_id = NULL, updated_at = %s
             WHERE id = %d AND status = 'processing' AND worker_id = %s",
            current_time('mysql'), $id, $workerId
        ));
        if ($changed === 1) {
            $this->storeAttempt($id, $this->resolveAttemptNumber($id), 'done');
        }
        return (int) $changed;
    }

    public function markErrorOwned(int $id, string $workerId, bool $retry, int $nextAt, string $message): int
    {
        global $wpdb;
        $status = $retry ? 'retry' : 'failed';
        $available = gmdate('Y-m-d H:i:s', $nextAt);
        $changed = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()}
             SET status = %s, attempts = attempts + 1, available_at = %s,
                 reserved_at = NULL, locked_at = NULL, worker_id = NULL,
                 last_error_code = 'queue_handler_exception', last_error_message = %s,
                 updated_at = %s
             WHERE id = %d AND status = 'processing' AND worker_id = %s",
            $status, $available, wp_strip_all_tags($message), current_time('mysql'), $id, $workerId
        ));
        if ($changed === 1) {
            $this->storeAttempt($id, $this->resolveAttemptNumber($id), $status, 'queue_handler_exception', $message);
        }
        return (int) $changed;
    }

    public function markDone($id): int
    {
        global $wpdb;

        $result = $wpdb->update(
            $this->tableName(),
            [
                'status' => 'done',
                'reserved_at' => null,
                'locked_at' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => absint($id)],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        $this->storeAttempt((int) $id, $this->resolveAttemptNumber((int) $id), 'done');

        return (int) $result;
    }

    /** @param array<string, mixed> $meta */
    public function markRetry(
        $id,
        $nextAttemptAtUnix,
        $errorCode = null,
        $errorMessage = null,
        array $meta = []
    ): int {
        global $wpdb;

        $id = absint($id);
        $availableAt = is_numeric($nextAttemptAtUnix)
            ? gmdate('Y-m-d H:i:s', (int) $nextAttemptAtUnix)
            : $this->normalizeDateTime($nextAttemptAtUnix) ?? gmdate('Y-m-d H:i:s', time() + 60);

        $sanitizedErrorCode = $errorCode !== null ? sanitize_key((string) $errorCode) : 'retry';
        $sanitizedErrorMessage = $errorMessage !== null
            ? wp_strip_all_tags((string) $errorMessage)
            : 'Retry scheduled';

        $lastError = wp_json_encode(
            [
                'message' => $sanitizedErrorMessage,
                'meta' => $meta,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()} SET
                status = 'retry',
                attempts = attempts + 1,
                available_at = %s,
                reserved_at = NULL,
                locked_at = NULL,
                last_error_code = %s,
                last_error_message = %s,
                last_error = %s,
                updated_at = %s
             WHERE id = %d",
            $availableAt,
            $sanitizedErrorCode,
            $sanitizedErrorMessage,
            $lastError ?: $sanitizedErrorMessage,
            current_time('mysql'),
            $id
        ));

        $this->storeAttempt(
            $id,
            $this->resolveAttemptNumber($id),
            'retry',
            $sanitizedErrorCode,
            $sanitizedErrorMessage
        );

        return (int) $result;
    }

    /** @param array<string, mixed> $meta */
    public function markFailed(
        $id,
        $errorCode = null,
        $errorMessage = null,
        array $meta = []
    ): int {
        global $wpdb;

        $id = absint($id);
        $sanitizedErrorCode = $errorCode !== null ? sanitize_key((string) $errorCode) : 'failed';
        $sanitizedErrorMessage = $errorMessage !== null
            ? wp_strip_all_tags((string) $errorMessage)
            : 'Queue job failed';

        $lastError = wp_json_encode(
            [
                'message' => $sanitizedErrorMessage,
                'meta' => $meta,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableName()} SET
                status = 'failed',
                attempts = attempts + 1,
                reserved_at = NULL,
                locked_at = NULL,
                last_error_code = %s,
                last_error_message = %s,
                last_error = %s,
                updated_at = %s
             WHERE id = %d",
            $sanitizedErrorCode,
            $sanitizedErrorMessage,
            $lastError ?: $sanitizedErrorMessage,
            current_time('mysql'),
            $id
        ));

        $this->storeAttempt(
            $id,
            $this->resolveAttemptNumber($id),
            'failed',
            $sanitizedErrorCode,
            $sanitizedErrorMessage
        );

        return (int) $result;
    }

    /**
     * @param string|null $errorCode
     * @param string|null $errorMessage
     */
    public function storeAttempt($jobId, $attemptNumber, $status, $errorCode = null, $errorMessage = null): int
    {
        global $wpdb;

        $result = $wpdb->insert($this->attemptsTableName(), [
            'job_id' => absint($jobId),
            'attempt_number' => absint($attemptNumber),
            'status' => sanitize_key((string) $status),
            'error_code' => $errorCode ? sanitize_key((string) $errorCode) : null,
            'error_message' => $errorMessage ? wp_strip_all_tags((string) $errorMessage) : null,
            'created_at' => current_time('mysql'),
        ]);

        return (int) $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        global $wpdb;

        $table = $this->tableName();
        $attemptsTable = $this->attemptsTableName();
        $staleBefore = wp_date('Y-m-d H:i:s', current_time('timestamp') - 600);

        $lastFailedAt = $wpdb->get_var("SELECT MAX(created_at) FROM {$attemptsTable} WHERE status = 'failed'");
        $oldestPending = $wpdb->get_var("SELECT MIN(created_at) FROM {$table} WHERE status IN ('pending','retry')");

        return [
            'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"),
            'processing' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'processing'"),
            'retry' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'retry'"),
            'failed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'failed'"),
            'dead_letter' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'dead_letter'"),
            'done' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'done'"),
            'queued_total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status IN ('pending','retry','processing')"),
            'stale_processing' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table}
                     WHERE status = 'processing'
                       AND reserved_at IS NOT NULL
                       AND reserved_at < %s",
                    $staleBefore
                )
            ),
            'oldest_pending_at' => $oldestPending ?: '—',
            'last_failed_attempt_at' => $lastFailedAt ?: '—',
            'last_done_at' => $wpdb->get_var("SELECT MAX(updated_at) FROM {$table} WHERE status = 'done'") ?: '—',
            'last_retry_at' => $wpdb->get_var("SELECT MAX(updated_at) FROM {$table} WHERE status = 'retry'") ?: '—',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentAttempts($limit = 20): array
    {
        global $wpdb;

        $limit = max(1, absint($limit));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT a.job_id, a.attempt_number, a.status, a.error_code, a.error_message, a.created_at,
                    j.event, j.order_id, j.chat_id
             FROM {$this->attemptsTableName()} a
             LEFT JOIN {$this->tableName()} j ON j.id = a.job_id
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function failedJobs($limit = 20): array
    {
        global $wpdb;

        $limit = max(1, absint($limit));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, event, order_id, chat_id, attempts, max_attempts, last_error_code, last_error_message, updated_at
             FROM {$this->tableName()}
             WHERE status = 'failed'
             ORDER BY updated_at DESC, id DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    /** @return array<string, mixed>|null */
    public function failedJobDetails(int $jobId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tableName()} WHERE id = %d AND status = 'failed' LIMIT 1",
            $jobId
        ), ARRAY_A);

        if (!is_array($row)) {
            return null;
        }

        $row['payload_decoded'] = json_decode((string) ($row['payload'] ?? ''), true);
        $row['last_error_decoded'] = json_decode((string) ($row['last_error'] ?? ''), true);

        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function attemptHistory(int $jobId): array
    {
        global $wpdb;

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT attempt_number, status, error_code, error_message, created_at
             FROM {$this->attemptsTableName()}
             WHERE job_id = %d
             ORDER BY id ASC",
            $jobId
        ), ARRAY_A);
    }

    public function clearStuckJobs(int $minutes = 10): int
    {
        global $wpdb;

        $minutes = max(1, (int) $minutes);
        $table = $this->tableName();
        $now = current_time('mysql');
        $staleBefore = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($minutes * 60));

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET
                status = 'retry',
                available_at = %s,
                reserved_at = NULL,
                locked_at = NULL,
                last_error_code = 'stale_processing',
                last_error_message = 'Released from stuck processing by dashboard action',
                updated_at = %s
             WHERE status = 'processing'
               AND reserved_at IS NOT NULL
               AND reserved_at < %s",
            $now,
            $now,
            $staleBefore
        ));
    }

    public function retryFailedJobs(): int
    {
        global $wpdb;

        $table = $this->tableName();
        $now = current_time('mysql');

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET
                status = 'retry',
                available_at = %s,
                reserved_at = NULL,
                locked_at = NULL,
                last_error_code = 'manual_retry',
                last_error_message = 'Retried manually from dashboard',
                updated_at = %s
             WHERE status = 'failed'",
            $now,
            $now
        ));
    }

    public function clearFailedJobs(): int
    {
        global $wpdb;

        return (int) $wpdb->query(
            "DELETE FROM {$this->tableName()} WHERE status = 'failed'"
        );
    }

    public function retryFailedJob(int $jobId): int
    {
        global $wpdb;

        $table = $this->tableName();
        $now = current_time('mysql');

        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$table}
             SET
                status = 'retry',
                available_at = %s,
                reserved_at = NULL,
                locked_at = NULL,
                last_error_code = 'manual_retry',
                last_error_message = 'Retried manually from dashboard',
                updated_at = %s
             WHERE id = %d
               AND status = 'failed'",
            $now,
            $now,
            $jobId
        ));
    }

    public function clearUndeliveredJobs(): int
    {
        global $wpdb;

        $jobsTable = $this->tableName();
        $attemptsTable = $this->attemptsTableName();

        $wpdb->query(
            "DELETE FROM {$attemptsTable}
             WHERE job_id IN (
                SELECT id FROM {$jobsTable}
                WHERE status IN ('pending', 'retry', 'processing', 'failed')
             )"
        );

        return (int) $wpdb->query(
            "DELETE FROM {$jobsTable}
             WHERE status IN ('pending', 'retry', 'processing', 'failed')"
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function undeliveredJobs(int $limit = 20): array
    {
        global $wpdb;

        $limit = max(1, absint($limit));

        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT id, event, order_id, chat_id, status, attempts, max_attempts, last_error_code, last_error_message, updated_at, created_at
             FROM {$this->tableName()}
             WHERE status IN ('pending', 'retry', 'processing', 'failed')
             ORDER BY updated_at DESC, id DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }


    public function cleanupOldJobs(int $doneDays = 14, int $failedDays = 30): int
    {
        global $wpdb;

        $jobsTable = $this->tableName();
        $attemptsTable = $this->attemptsTableName();
        $doneDays = max(1, $doneDays);
        $failedDays = max($doneDays, $failedDays);
        $doneBefore = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($doneDays * DAY_IN_SECONDS));
        $failedBefore = wp_date('Y-m-d H:i:s', current_time('timestamp') - ($failedDays * DAY_IN_SECONDS));

        $ids = (array) $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$jobsTable}
             WHERE (status = 'done' AND updated_at < %s)
                OR (status IN ('failed','dead_letter') AND updated_at < %s)",
            $doneBefore,
            $failedBefore
        ));

        if ($ids === []) {
            return 0;
        }

        $ids = array_map('absint', $ids);
        $in = implode(',', $ids);
        $wpdb->query("DELETE FROM {$attemptsTable} WHERE job_id IN ({$in})");

        return (int) $wpdb->query("DELETE FROM {$jobsTable} WHERE id IN ({$in})");
    }

    /**
     * @param string|int|null $value
     */
    private function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return gmdate('Y-m-d H:i:s', (int) $value);
        }

        $timestamp = strtotime((string) $value);

        return $timestamp ? gmdate('Y-m-d H:i:s', $timestamp) : null;
    }

    private function resolveAttemptNumber(int $jobId): int
    {
        global $wpdb;

        $attempts = $wpdb->get_var(
            $wpdb->prepare("SELECT attempts FROM {$this->tableName()} WHERE id = %d", $jobId)
        );

        return max(1, (int) $attempts);
    }
}
