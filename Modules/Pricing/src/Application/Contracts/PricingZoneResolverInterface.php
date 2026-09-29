<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\Pricing\Application\Dto\PricingLaneDto;
use Modules\Pricing\Application\Dto\ZoneResolutionDto;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;

interface PricingZoneResolverInterface extends CommitmentZoneResolverInterface
{
    public function resolveZone(string $versionId, CoverageAddress $party, string $partyName): ZoneResolutionDto;

    public function resolveLane(string $versionId, CoverageAddress $origin, CoverageAddress $destination): PricingLaneDto;

    public function resolveEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): string;

    public function findEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): ?string;
}
