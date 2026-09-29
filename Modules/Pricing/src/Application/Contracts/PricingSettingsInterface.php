<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

interface PricingSettingsInterface
{
    public function quoteTtlSeconds(): int;
}
