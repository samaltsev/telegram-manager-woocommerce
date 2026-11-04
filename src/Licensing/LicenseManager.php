<?php
declare(strict_types=1);

namespace WCTM\Licensing;

if (!defined('ABSPATH')) {
    exit;
}

final class LicenseManager
{
    private const OPTION_KEY = 'wctm_license';

    public function getPlan(): string
    {
        $license = get_option(self::OPTION_KEY, []);
        $plan = is_array($license) ? sanitize_key((string) ($license['plan'] ?? Plan::FREE)) : Plan::FREE;

        return $plan === Plan::PRO ? Plan::PRO : Plan::FREE;
    }

    public function isPro(): bool
    {
        return $this->getPlan() === Plan::PRO;
    }

    public function setPlan(string $plan): void
    {
        $plan = sanitize_key($plan);
        update_option(self::OPTION_KEY, ['plan' => $plan === Plan::PRO ? Plan::PRO : Plan::FREE]);
    }
}
