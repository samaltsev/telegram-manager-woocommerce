<?php
declare(strict_types=1);
namespace WCTM\Domain\Queue\Entity;

if (!defined('ABSPATH')) {
    exit;
}

class QueueJob
{
    protected $row;

    public function __construct(array $row)
    {
        $this->row = $row;
    }

    public function id() { return (int) ($this->row['id'] ?? 0); }
    public function jobType() { return (string) ($this->row['job_type'] ?? ($this->row['event'] ?? 'send_telegram_message')); }
    public function event() { return (string) ($this->row['event'] ?? ($this->payload()['event'] ?? $this->jobType())); }
    public function orderId() { return !empty($this->row['order_id']) ? (int) $this->row['order_id'] : (int) ($this->payload()['order_id'] ?? 0); }
    public function chatId() { return (string) ($this->row['chat_id'] ?? ($this->payload()['chat_id'] ?? '')); }
    public function messageThreadId() {
        if (array_key_exists('message_thread_id', $this->row) && $this->row['message_thread_id'] !== null && $this->row['message_thread_id'] !== '') {
            return (int) $this->row['message_thread_id'];
        }
        $payload = $this->payload();
        return isset($payload['message_thread_id']) && $payload['message_thread_id'] !== null && $payload['message_thread_id'] !== '' ? (int) $payload['message_thread_id'] : null;
    }
    public function payload() {
        if (is_array($this->row['payload'] ?? null)) {
            return $this->row['payload'];
        }
        if (is_array($this->row['payload_json'] ?? null)) {
            return $this->row['payload_json'];
        }
        return array();
    }
    public function attempts() { return (int) ($this->row['attempts'] ?? 0); }
    public function maxAttempts() { return (int) ($this->row['max_attempts'] ?? 1); }
    public function traceId() { return isset($this->row['trace_id']) ? (string) $this->row['trace_id'] : ''; }
    public function idempotencyKey() { return isset($this->row['idempotency_key']) ? (string) $this->row['idempotency_key'] : ''; }
    public function workerId() { return isset($this->row['worker_id']) ? (string) $this->row['worker_id'] : ''; }
    public function raw() { return $this->row; }
}
