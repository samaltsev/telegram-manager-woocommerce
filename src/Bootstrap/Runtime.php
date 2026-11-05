<?php
declare(strict_types=1);
namespace WCTM\Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}

final class Runtime
{
    private static ?Kernel $kernel = null;

    public static function boot(Kernel $kernel): void
    {
        self::$kernel = $kernel;
    }

    public static function kernel(): ?Kernel
    {
        return self::$kernel;
    }

    public static function container(): ?Container
    {
        return self::$kernel ? self::$kernel->container() : null;
    }

    public static function has(string $id): bool
    {
        $container = self::container();
        return $container ? $container->has($id) : false;
    }

    public static function get(string $id)
    {
        $container = self::container();
        if (!$container) {
            throw new \RuntimeException('WCTM runtime container is not booted.');
        }

        return $container->get($id);
    }
}
