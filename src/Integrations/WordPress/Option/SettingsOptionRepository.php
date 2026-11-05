<?php
declare(strict_types=1);

namespace WCTM\Integrations\WordPress\Option;

if (!defined('ABSPATH')) {
    exit;
}

final class SettingsOptionRepository
{
    public const OPTION_KEY = 'wctm_settings';

    public function __construct(
        private BotSettingsRepository $bots,
        private NotificationSettingsRepository $notifications,
        private QueueSettingsRepository $queue,
        private TemplateSettingsRepository $templates,
        private FeatureFlagsRepository $features
    ) {
    }

    public function defaults(): array
    {
        return array_merge(
            $this->bots->defaults(),
            $this->notifications->defaults(),
            $this->queue->defaults(),
            $this->templates->defaults(),
            $this->features->defaults()
        );
    }

    public function all(): array
    {
        return wp_parse_args((array) get_option(self::OPTION_KEY, []), $this->defaults());
    }

    public function get($key, $default = null)
    {
        $all = $this->all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public function update(array $data): void
    {
        update_option(self::OPTION_KEY, array_merge((array) get_option(self::OPTION_KEY, []), $this->sanitize($data)));
    }

    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        return array_merge(
            $this->bots->sanitize($input),
            $this->notifications->sanitize($input),
            $this->queue->sanitize($input),
            $this->templates->sanitize($input),
            $this->features->sanitize($input)
        );
    }

    public function statusWhitelist(): array
    {
        return $this->notifications->statusWhitelist();
    }
}
