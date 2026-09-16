<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Adapters;

final class LaravelPricingSettings implements \Modules\Pricing\Application\Contracts\PricingSettings
{
    public function quoteTtlSeconds(): int
    {
        return (int) config('chabok.pricing.quote_ttl_seconds', 900);
    }
}
