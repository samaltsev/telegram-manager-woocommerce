<?php
declare(strict_types=1);
namespace WCTM\Infrastructure\Queue;

if (!defined('ABSPATH')) {
    exit;
}

class Backoff
{
    public static function exponential($attempt)
    {
        $attempt = max(1, (int) $attempt);
        return (int) min(3600, pow(2, $attempt - 1) * 60);
    }
}
