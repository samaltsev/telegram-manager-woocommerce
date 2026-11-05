<?php
declare(strict_types=1);
if (!defined('ABSPATH')) {
    exit;
}
$title = (string) ($title ?? __('Telegram Control Center', 'telegram-manager-woocommerce'));
$subtitle = (string) ($subtitle ?? __('Reliable Telegram order notifications with queue, retry and logs.', 'telegram-manager-woocommerce'));
$badgeValue = $badge ?? '';
$badge = is_scalar($badgeValue) ? (string) $badgeValue : '';
$actions = is_array($actions ?? null) ? $actions : [];
$botImage = defined('WCTM_URL') ? WCTM_URL . 'assets/admin/img/bot.png' : '';
?>
<header class="tcc-page-header">
    <div class="tcc-brand-row">
        <?php if ($botImage !== '') : ?>
            <img class="tcc-logo" src="<?php echo esc_url($botImage); ?>" alt="" />
        <?php endif; ?>
        <div>
            <h1>
                <?php echo esc_html($title); ?>
                <?php if ($badge !== '') : ?>
                    <span class="tcc-badge tcc-badge-info"><?php echo esc_html($badge); ?></span>
                <?php endif; ?>
            </h1>
            <p><?php echo esc_html($subtitle); ?></p>
        </div>
    </div>

    <?php if (!empty($actions)) : ?>
        <div class="tcc-header-actions">
            <?php foreach ($actions as $action) : ?>
                <?php if (($action['type'] ?? 'link') === 'text') : ?>
                    <span class="tcc-version"><?php echo esc_html((string) ($action['label'] ?? '')); ?></span>
                <?php else : ?>
                    <a class="<?php echo esc_attr((string) ($action['class'] ?? 'tcc-btn tcc-btn-secondary')); ?>" href="<?php echo esc_url((string) ($action['url'] ?? '#')); ?>" <?php echo !empty($action['external']) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html((string) ($action['label'] ?? '')); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</header>
