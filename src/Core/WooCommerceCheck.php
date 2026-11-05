<?php
declare(strict_types=1);
namespace WCTM\Core;

if (!defined('ABSPATH')) {
    exit;
}

class WooCommerceCheck
{
    public static function isActive()
    {
        return class_exists('WooCommerce');
    }
}
