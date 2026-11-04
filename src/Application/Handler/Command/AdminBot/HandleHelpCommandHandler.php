<?php
declare(strict_types=1);

namespace WCTM\Application\Handler\Command\AdminBot;

if (!defined('ABSPATH')) {
    exit;
}

final class HandleHelpCommandHandler
{
    public function handle(): string
    {
        return "🆘 Telegram Control Center\n\n"
            . "Free:\n"
            . "/today — today's order summary\n"
            . "/orders — latest orders\n\n"
            . "Pro:\n"
            . "/revenue — today's revenue\n"
            . "/revenue_week — weekly revenue\n"
            . "/revenue_month — monthly revenue\n"
            . "/lowstock — low stock products\n"
            . "/support — customer messages\n"
            . "/settings — notification settings\n"
            . "/stop — pause admin alerts";
    }
}
