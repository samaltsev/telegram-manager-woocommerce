<?php
declare(strict_types=1);

namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

final class TemplateSettingsRepository
{
    public const OPTION_KEY = 'wctm_settings';

    public function defaults(): array
    {
        return [
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 0,
            'template_new_order' => "🛒 Order #{order_number}\n\n📦 Items: {items_count}\n{items_short}\n\n💰 {order_total}",
            'template_status_changed' => "Order #{order_number}\n\n💬 Status changed from “{old_order_status}” to “{new_order_status}”.\n\n📦 Items: {items_count}\n{items_short}\n\n💰 {order_total}",
            'template_refund_created' => "💸 Refund for order #{order_number}\n\n📦 Items: {items_count}\n{items_short}\n\n💰 {order_total}",
        ];
    }

    public function all(): array
    {
        return wp_parse_args((array) get_option(self::OPTION_KEY, []), $this->defaults());
    }

    public function get(string $key, $default = null)
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    public function update(array $data): void
    {
        update_option(self::OPTION_KEY, array_merge((array) get_option(self::OPTION_KEY, []), $this->sanitize($data)));
    }

    public function sanitize(array $input): array
    {
        $out = $this->all();
        if (isset($input['parse_mode']) && in_array($input['parse_mode'], ['HTML', 'Markdown', 'MarkdownV2'], true)) {
            $out['parse_mode'] = $input['parse_mode'];
        }
        $out['disable_web_page_preview'] = !empty($input['disable_web_page_preview']) ? 1 : 0;
        foreach (array_keys($this->defaults()) as $key) {
            if (str_starts_with($key, 'template_') && isset($input[$key])) {
                $out[$key] = wp_kses_post(wp_unslash((string) $input[$key]));
            }
        }
        return array_intersect_key($out, $this->defaults());
    }

    public function getParseMode(): string
    {
        return (string) $this->get('parse_mode', 'HTML');
    }
}
