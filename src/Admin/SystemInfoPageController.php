<?php
declare(strict_types=1);

namespace WCTM\Admin;

use WCTM\Support\SystemInfo;

if (!defined('ABSPATH')) {
    exit;
}

final class SystemInfoPageController
{
    public function __construct(private SystemInfo $systemInfo)
    {
    }

    public function render(): void
    {
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            wp_die(esc_html__('Access denied.', 'telegram-manager-woocommerce'));
        }

        $data = $this->systemInfo->data();
        ViewRenderer::render('system', $data);
    }
}
