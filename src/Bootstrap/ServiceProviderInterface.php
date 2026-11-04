<?php
declare(strict_types=1);

namespace WCTM\Bootstrap;

if (!defined('ABSPATH')) {
    exit;
}

interface ServiceProviderInterface
{
    public function register(Container $container, HooksRegistrar $hooks): void;
}
