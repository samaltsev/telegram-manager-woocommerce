<?php
declare(strict_types=1);

namespace WCTM\Integrations\Telegram\Debug;

use WCTM\Integrations\Telegram\Client\TelegramClient;
use WCTM\Integrations\WordPress\Option\BotOptionRepository;

if (!defined('ABSPATH')) {
    exit;
}

class TelegramConnectionVerifyer
{
    protected TelegramClient $client;
    protected BotOptionRepository $botOptions;

    public function __construct(TelegramClient $client, BotOptionRepository $botOptions)
    {
        $this->client = $client;
        $this->botOptions = $botOptions;
    }

    public function getMe($token = '')
    {
        return $this->client->getMe($token ?: $this->botOptions->getDefaultToken());
    }

    public function getUpdates($token = '', array $args = array())
    {
        return $this->client->getUpdates($token ?: $this->botOptions->getDefaultToken(), $args);
    }
}
