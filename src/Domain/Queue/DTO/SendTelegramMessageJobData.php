<?php
declare(strict_types=1);
namespace WCTM\Domain\Queue\DTO;

if (!defined('ABSPATH')) {
    exit;
}

class SendTelegramMessageJobData
{
    protected $botType;
    protected $chatId;
    protected $text;
    protected $extra;
    protected $messageThreadId;
    protected $orderId;
    protected $event;

    public function __construct($botType, $chatId, $text, array $extra = array(), $messageThreadId = null, $orderId = null, $event = 'send_telegram_message')
    {
        $this->botType = (string) $botType;
        $this->chatId = (string) $chatId;
        $this->text = (string) $text;
        $this->extra = $extra;
        $this->messageThreadId = $messageThreadId !== null && $messageThreadId !== '' ? (int) $messageThreadId : null;
        $this->orderId = $orderId ? (int) $orderId : 0;
        $this->event = (string) $event;
    }

    public function botType() { return $this->botType; }
    public function chatId() { return $this->chatId; }
    public function text() { return $this->text; }
    public function extra() { return $this->extra; }
    public function messageThreadId() { return $this->messageThreadId; }
    public function orderId() { return $this->orderId; }
    public function event() { return $this->event; }

    public function toArray()
    {
        return array(
            'bot_type' => $this->botType,
            'chat_id' => $this->chatId,
            'text' => $this->text,
            'extra' => $this->extra,
            'message_thread_id' => $this->messageThreadId,
            'order_id' => $this->orderId,
            'event' => $this->event,
        );
    }

    public static function fromArray(array $data)
    {
        return new self(
            isset($data['bot_type']) ? $data['bot_type'] : 'admin',
            isset($data['chat_id']) ? $data['chat_id'] : '',
            isset($data['text']) ? $data['text'] : '',
            isset($data['extra']) && is_array($data['extra']) ? $data['extra'] : array(),
            isset($data['message_thread_id']) ? $data['message_thread_id'] : null,
            isset($data['order_id']) ? $data['order_id'] : null,
            isset($data['event']) ? $data['event'] : 'send_telegram_message'
        );
    }
}
