<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

$templates = [
    'template_new_order' => __('New order notification', 'telegram-manager-woocommerce'),
    'template_status_changed' => __('Completed / Cancelled / Refunded notification', 'telegram-manager-woocommerce'),
    'template_refund_created' => __('Refund notification', 'telegram-manager-woocommerce'),
];
?>
<section class="wc-tg-card">
    <h2><?php esc_html_e('Free notification templates', 'telegram-manager-woocommerce'); ?></h2>
    <p><?php esc_html_e('Free template principle: order → products → total → Open Order button.', 'telegram-manager-woocommerce'); ?></p>
    <?php foreach ($templates as $key => $label) : ?>
        <div class="wc-tg-form-group">
            <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
            <textarea id="<?php echo esc_attr($key); ?>" class="large-text code" rows="6" name="wctm_settings[<?php echo esc_attr($key); ?>]"><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea>
        </div>
    <?php endforeach; ?>
</section>
