<?php
declare(strict_types=1);
namespace WCTM\Notifications;

class NotificationTemplateService
{
    protected array $templates = [];

    public function addTemplate(string $key, string $template)
    {
        $this->templates[$key] = $template;
    }

    public function getTemplate(string $key, array $vars = []): string
    {
        $text = $this->templates[$key] ?? '';
        foreach ($vars as $name => $value) {
            $text = str_replace("{{$name}}", esc_html($value), $text);
        }
        return $text;
    }
}