<?php
declare(strict_types=1);
namespace WCTM\Integrations\Telegram\Webhook;

if (!defined('ABSPATH')) {
    exit;
}

class TelegramUpdateParser
{
    public function parse(array $payload)
    {
        $payload = is_array($payload) ? $payload : array();

        return array(
            'message' => isset($payload['message']) && is_array($payload['message']) ? $payload['message'] : array(),
            'callback_query' => isset($payload['callback_query']) && is_array($payload['callback_query']) ? $payload['callback_query'] : array(),
            'raw' => $payload,
        );
    }
}
