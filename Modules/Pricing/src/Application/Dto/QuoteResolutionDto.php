<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\ValueObjects\CalculationFacts;
use Modules\Pricing\Domain\ValueObjects\PricingRule;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\ServiceCatalog\Application\Dto\ServiceEligibilitySelectionDto;

/** Resolution is local to one quote; it is never cached or reused after a mutation. */
final class QuoteResolutionDto
{
    /** @var list<ServiceTariffEvidenceDto> */
    public array $dependencyEvidence = [];

    /** @param array<string, true> $selectedOptionIdentities @param list<string> $currentOptionVersions @param list<PricingRule> $calculationRules @param list<int>|null $zoneRanks */
    public function __construct(
        public TariffVersionRecord $tariff,
        public ServiceEligibilitySelectionDto $offering,
        public PricingLaneDto $lane,
        public PricingZoneRecord $basisZone,
        public CalculationFacts $facts,
        public string $resolvedZoneSetVersionId,
        public ?string $matrixCell,
        public array $selectedOptionIdentities,
        public array $currentOptionVersions,
        public array $calculationRules,
        public ?array $zoneRanks = null,
    ) {}
}
