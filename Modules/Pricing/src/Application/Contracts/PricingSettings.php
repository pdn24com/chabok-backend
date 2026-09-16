<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

interface PricingSettings
{
    public function quoteTtlSeconds(): int;
}
