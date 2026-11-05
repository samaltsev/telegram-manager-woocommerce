<?php
declare(strict_types=1);
namespace WCTM\Integrations\Telegram\Diagnostics;

use WCTM\Integrations\Telegram\Debug\TelegramConnectionVerifyer;

if (!defined('ABSPATH')) {
    exit;
}

class TelegramConnectionTester extends TelegramConnectionVerifyer
{
}
