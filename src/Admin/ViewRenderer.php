<?php
declare(strict_types=1);

namespace WCTM\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class ViewRenderer
{
    /**
     * @param array<string,mixed> $data
     */
    public static function render(string $view, array $data = []): void
    {
        $view = sanitize_key(str_replace(['/', '\\'], '-', $view));
        $file = WCTM_PATH . 'views/' . $view . '.php';

        if (!is_file($file)) {
            wp_die(esc_html(sprintf('Admin view not found: %s', $view)));
        }

        extract($data, EXTR_SKIP);
        include $file;
    }
}
