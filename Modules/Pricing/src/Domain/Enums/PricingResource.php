<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

enum PricingResource: string
{
    /** Resolves a route segment, or reports the route as unknown. */
    public static function fromPath(string $segment): self
    {
        return self::tryFrom($segment) ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
    }

    public function identityKey(): string
    {
        return $this === self::Tariffs ? 'tariff_family_id' : 'pricing_zone_set_id';
    }

    public function versionKey(): string
    {
        return $this === self::Tariffs ? 'tariff_version_id' : 'zone_set_version_id';
    }
    case Tariffs = 'tariffs';
    case ZoneSets = 'zone-sets';
}
