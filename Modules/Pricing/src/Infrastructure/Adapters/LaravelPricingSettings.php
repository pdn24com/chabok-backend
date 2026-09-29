<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Adapters;

use Modules\Pricing\Application\Contracts\PricingSettingsInterface;

final class LaravelPricingSettings implements PricingSettingsInterface
{
    public function quoteTtlSeconds(): int
    {
        return (int) config('chabok.pricing.quote_ttl_seconds', 900);
    }
}
