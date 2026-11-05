<?php
declare(strict_types=1);
namespace WCTM\Templates;

if (!defined('ABSPATH')) exit;

class TemplateParser
{
    protected string $parseMode = 'HTML';

    public function __construct(string $parseMode = 'HTML')
    {
        $this->parseMode = $parseMode;
    }

    public function setParseMode(string $mode): void { $this->parseMode = $mode; }
    public function getParseMode(): string { return $this->parseMode; }

    public function parse(string $template, array $tags): string
    {
        return $this->processTemplate($template, $tags);
    }

    public function parseWithImage(string $template, array $tags): array
    {
        $imageUrl = $tags['product_image'] ?? '';
        $imageUrl = filter_var($imageUrl, FILTER_VALIDATE_URL) ?: '';

        unset($tags['product_image']);
        $text = $this->processTemplate($template, $tags);

        // Ограничение длины текста Telegram
        if (strlen($text) > 4096) {
            $text = substr($text, 0, 4096);
        }

        return [
            'text' => trim($text),
            'image_url' => $imageUrl,
            'has_image' => !empty($imageUrl),
            'parse_mode' => $this->parseMode
        ];
    }

    private function processTemplate(string $template, array $tags): string
    {
        $replace = [];
        foreach ($tags as $key => $value) {
            $replace['{' . $key . '}'] = esc_html((string)$value);
        }

        $text = strtr($template, $replace);
        $text = $this->processProductImageTag($text);

        if ($this->parseMode === 'HTML') {
            $text = $this->sanitizeHtmlForTelegram($text);
        } else {
            $text = $this->convertToMarkdown($text);
        }

        return preg_replace('/\{[a-zA-Z0-9_]+\}/', '—', $text);
    }

    private function processProductImageTag(string $text): string
    {
        return preg_replace('/\{product_image\}\s*/', '', $text);
    }

    protected function sanitizeHtmlForTelegram(string $text): string
    {
        $text = preg_replace('/<span[^>]*>(.*?)<\/span>/is', '$1', $text);
        $allowedTags = '<b><strong><i><em><u><ins><s><strike><del><code><pre><a>';
        $text = strip_tags($text, $allowedTags);
        return $text;
    }

    protected function convertToMarkdown(string $text): string
    {
        $map = [
            '/<(b|strong)>(.*?)<\/\1>/is' => '**$2**',
            '/<(i|em)>(.*?)<\/\1>/is' => '_$2_',
            '/<(u|ins)>(.*?)<\/\1>/is' => '__$2__',
            '/<(s|strike|del)>(.*?)<\/\1>/is' => '~$2~',
            '/<code>(.*?)<\/code>/is' => '`$1`',
            '/<pre>(.*?)<\/pre>/is' => "```$1```",
            '/<a\s+href=["\']([^"\']+)["\']>(.*?)<\/a>/is' => '[$2]($1)',
            '/<span[^>]*>(.*?)<\/span>/is' => '$1'
        ];

        foreach ($map as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        $text = strip_tags($text);
        return $this->escapeMarkdownChars($text);
    }

    protected function escapeMarkdownChars(string $text): string
    {
        if ($this->parseMode === 'MarkdownV2') {
            $chars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
            foreach ($chars as $char) {
                $text = str_replace($char, '\\' . $char, $text);
            }
        }
        return $text;
    }
}