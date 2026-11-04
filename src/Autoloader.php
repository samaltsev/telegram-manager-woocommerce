<?php
declare(strict_types=1);
namespace WCTM;

if (!defined('ABSPATH')) {
    exit;
}

class Autoloader
{
    public static function register()
    {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    public static function autoload($class)
    {
        if (strpos($class, 'WCTM\\') !== 0) {
            return;
        }

        $relative = substr($class, 5);
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $path = WCTM_PATH . 'src/' . $relative . '.php';

        if (file_exists($path)) {
            require_once $path;
        }
    }
}
