<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Modules\Pricing\Application\Repositories\PricingZoneSetRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final class EloquentPricingZoneSetRepository implements PricingZoneSetRepositoryInterface
{
    public function visibleVersionIdsAmong(array $versionIds, ?string $hqId): array
    {
        return PricingZoneSetVersionRecord::query()->whereIn('zone_set_version_id', $versionIds)
            ->whereHas('zoneSet', fn ($identity) => $identity->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId)))
            ->pluck('zone_set_version_id')->all();
    }
}
