<?php
declare(strict_types=1);
namespace WCTM\Telegram;

if (!defined('ABSPATH')) {
    exit;
}

class UpdatesHelper
{
    public static function extractChatTargets(array $updates)
    {
        $targets = array();
        foreach (($updates['result'] ?? array()) as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? array();
            if (empty($message['chat']['id'])) {
                continue;
            }
            $chatId = (string) $message['chat']['id'];
            $threadId = isset($message['message_thread_id']) ? (int) $message['message_thread_id'] : null;
            $targets[$chatId . '|' . $threadId] = array(
                'chat_id' => $chatId,
                'message_thread_id' => $threadId,
                'type' => $message['chat']['type'] ?? '',
                'title' => $message['chat']['title'] ?? '',
                'username' => $message['chat']['username'] ?? '',
                'date' => isset($message['date']) ? (int) $message['date'] : 0,
            );
        }
        usort($targets, static function ($a, $b) {
            return ($b['date'] ?? 0) <=> ($a['date'] ?? 0);
        });
        return array_values($targets);
    }

    public static function preferredChatId(array $targets)
    {
        foreach ($targets as $target) {
            if (($target['type'] ?? '') === 'private' && !empty($target['chat_id'])) {
                return (string) $target['chat_id'];
            }
        }
        return !empty($targets[0]['chat_id']) ? (string) $targets[0]['chat_id'] : '';
    }
}
