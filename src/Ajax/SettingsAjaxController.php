<?php
declare(strict_types=1);

namespace WCTM\Ajax;

use WCTM\Integrations\WordPress\Option\BotSettingsRepository;
use WCTM\Integrations\WordPress\Option\TemplateSettingsRepository;
use WCTM\Templates\TemplateParser;
use WCTM\Templates\TemplateTags;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsAjaxController extends AbstractAjaxController
{
    public function __construct(
        BotSettingsRepository $botSettings,
        private TemplateSettingsRepository $templates,
        private TemplateParser $parser
    ) {
        parent::__construct($botSettings);
    }

    public function save_templates(): void
    {
        $this->guard();
        $data = [];
        foreach ([
            'template_new_order',
            'template_status_changed',
            'template_refund_created',
            'parse_mode',
            'disable_web_page_preview',
        ] as $key) {
            if (isset($_POST[$key])) {
                $data[$key] = wp_unslash($_POST[$key]);
            }
        }
        $data['disable_web_page_preview'] = !empty($data['disable_web_page_preview']) ? 1 : 0;
        $this->templates->update($data);
        wp_send_json_success(['message' => __('Settings saved.', 'telegram-manager-woocommerce')]);
    }

    public function preview_template(): void
    {
        $this->guard();
        $template = isset($_POST['template']) ? wp_kses_post(wp_unslash($_POST['template'])) : '';
        $order = function_exists('wc_get_orders')
            ? current(wc_get_orders(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC']))
            : false;
        $tags = $order ? TemplateTags::forOrder($order) : [
            'order_number' => '1234',
            'customer_name' => 'John Doe',
            'order_total' => '$150.00',
            'order_items_text' => '- Product x 3',
            'admin_order_url' => admin_url(),
        ];
        wp_send_json_success(['preview' => $this->parser->parse($template, $tags)]);
    }
}
