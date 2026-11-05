<?php
declare(strict_types=1);

namespace WCTM\Telegram\Ui;

if (!defined('ABSPATH')) {
    exit;
}

final class MessageFormatter
{
    public static function block(string $title, array $lines = [], ?string $hint = null): string
    {
        $parts = [trim($title)];

        $normalizedLines = self::normalizeLines($lines);
        if (!empty($normalizedLines)) {
            $parts[] = implode("\n", $normalizedLines);
        }

        $normalizedHint = self::normalizeText($hint);
        if ($normalizedHint !== '') {
            $parts[] = $normalizedHint;
        }

        return implode("\n\n", $parts);
    }

    public static function success(string $title, array $lines = [], ?string $hint = null): string
    {
        return self::block('✅ ' . trim($title), $lines, $hint);
    }

    public static function info(string $title, array $lines = [], ?string $hint = null): string
    {
        return self::block('ℹ️ ' . trim($title), $lines, $hint);
    }

    public static function warning(string $title, array $lines = [], ?string $hint = null): string
    {
        return self::block('⚠️ ' . trim($title), $lines, $hint);
    }

    public static function error(string $title, array $lines = [], ?string $hint = null): string
    {
        return self::block('❌ ' . trim($title), $lines, $hint);
    }

    public static function menu(string $title, array $items, ?string $hint = null): string
    {
        return self::block('🧭 ' . trim($title), self::normalizeLines($items), $hint);
    }

    public static function listBlock(string $title, array $items, ?string $hint = null): string
    {
        return self::block(trim($title), self::normalizeLines($items), $hint);
    }

    public static function keyValueList(string $title, array $pairs, ?string $hint = null): string
    {
        $lines = [];

        foreach ($pairs as $label => $value) {
            $label = self::normalizeText((string) $label);
            $value = self::normalizeText((string) $value);

            if ($label === '' && $value === '') {
                continue;
            }

            if ($label === '') {
                $lines[] = $value;
                continue;
            }

            if ($value === '') {
                $lines[] = $label;
                continue;
            }

            $lines[] = sprintf('%s: %s', $label, $value);
        }

        return self::block(trim($title), $lines, $hint);
    }

    /**
     * @param array<int|string, mixed> $lines
     * @return array<int, string>
     */
    private static function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $text = self::normalizeText(is_scalar($line) || $line === null ? (string) $line : '');
            if ($text === '') {
                continue;
            }

            $normalized[] = $text;
        }

        return $normalized;
    }

    private static function normalizeText(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = explode("\n", $text);
        $lines = array_map(
            static fn(string $line): string => trim($line),
            $lines
        );

        $text = implode("\n", $lines);

        return trim($text);
    }
}