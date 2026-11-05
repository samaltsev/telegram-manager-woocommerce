<?php
declare(strict_types=1);
namespace WCTM\Telegram;

if (!defined('ABSPATH')) {
    exit;
}

class ChatTarget
{
    public $chatId;
    public $threadId;

    public function __construct($chatId, $threadId = null)
    {
        $this->chatId = (string) $chatId;
        $this->threadId = $threadId !== null && $threadId !== '' ? (int) $threadId : null;
    }

    public static function parseMany($raw)
    {
        $items = array();
        foreach (preg_split('/[\r\n]+/', (string) $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $items[] = new self($parts[0] ?? '', $parts[1] ?? null);
        }
        return array_filter($items, static function ($target) {
            return $target->chatId !== '';
        });
    }

    public function toArray()
    {
        return array('chat_id' => $this->chatId, 'message_thread_id' => $this->threadId);
    }
}
