<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class PricingSimulationDto
{
    /** @param array<string, mixed> $resolutionEvidence Immutable JSON evidence for the public/storage boundary. @param list<string> $warnings */
    public function __construct(
        public CalculationResult $calculation,
        public TariffVersionRecord $tariff,
        public string $zoneSetVersionId,
        public CarbonImmutable $calculatedAt,
        public array $resolutionEvidence,
        public array $warnings,
    ) {}
}
