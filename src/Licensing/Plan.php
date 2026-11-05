<?php
declare(strict_types=1);

namespace WCTM\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

final class Plan
{
    public const FREE = 'free';
    public const PRO = 'pro';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::FREE => 'Free',
            self::PRO => 'Pro',
        ];
    }
}
