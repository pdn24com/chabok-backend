<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

interface PricingZoneSetRepositoryInterface
{
    /** Zone-set version ids among the candidates whose Zone Set is visible to the tenant. @param list<string> $versionIds @return list<string> */
    public function visibleVersionIdsAmong(array $versionIds, ?string $hqId): array;
}
