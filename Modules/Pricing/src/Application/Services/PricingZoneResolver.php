<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingZoneResolver
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\CommitmentZoneReader $commitmentZones,
    )
    {
    }

    public function resolveEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): string
    {
        $identityId = $this->pricing->zoneGroup($configuredVersionId);
        if ($identityId === null) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'Pricing Zone Set version could not be resolved.', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }
        $versions = $this->pricing->effectiveZoneVersions($identityId, $asOf);
        if ($versions === []) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'No effective published Pricing Zone Set version was found.', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }
        // Version order defines the effective successor; earlier records remain immutable.
        return (string) $versions[0];
    }

    public function resolveZone(string $versionId, array $party, string $partyName): array
    {
        return $this->commitmentZones->resolveZone($versionId, $party, $partyName);
    }
}
