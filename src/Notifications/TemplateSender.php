<?php
declare(strict_types=1);

namespace WCTM\Notifications;

use WCTM\Logger\Logger;
use WCTM\Templates\TemplateParser;

if (!defined('ABSPATH')) {
    exit;
}

class TemplateSender
{
    protected $telegram;
    protected TemplateParser $parser;

    public function __construct($telegramClient)
    {
        $this->telegram = $telegramClient;
        $this->parser = new TemplateParser();
    }

    public function sendTemplateMessage($chatId, string $template, array $tags, string $parseMode = 'HTML'): array
    {
        try {
            $allowedModes = ['HTML', 'Markdown', 'MarkdownV2'];
            $parseMode = in_array($parseMode, $allowedModes, true) ? $parseMode : 'HTML';

            $parsed = $this->parser->parseWithImage($template, $tags);

            if ($parsed['has_image'] && filter_var($parsed['image_url'], FILTER_VALIDATE_URL)) {
                $result = $this->telegram->sendPhoto([
                    'chat_id' => $chatId,
                    'photo' => $parsed['image_url'],
                    'caption' => $parsed['text'],
                    'parse_mode' => $parseMode,
                ]);
            } else {
                $result = $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $parsed['text'],
                    'parse_mode' => $parseMode,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Message sent',
                'result' => $result ?? null,
            ];
        } catch (\Exception $e) {
            Logger::error('template_sender_failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Send error: ' . $e->getMessage(),
            ];
        }
    }
}
