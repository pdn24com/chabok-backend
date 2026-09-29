<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Pricing\Application\Dto\PricingSimulationDto;
use Modules\Pricing\Application\Serialization\PricingCalculationSerializer;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;

/** @mixin PricingSimulationDto */
final class PricingSimulationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...PricingCalculationSerializer::serialize($this->calculation),
            'mode' => 'DRAFT',
            'acceptable' => false,
            'tariff_version_id' => $this->tariff->tariff_version_id,
            'lock_version' => (int) $this->tariff->lock_version,
            'zone_set_version_id' => $this->zoneSetVersionId,
            'currency' => Currency::Irr->value,
            'calculated_at' => $this->calculatedAt->toISOString(),
            'resolution_evidence' => $this->resolutionEvidence,
            'warnings' => $this->warnings,
            'lines' => array_map(static fn (CalculationLine $line): array => [
                ...PricingCalculationSerializer::line($line),
                'charge_type_code' => $line->rule->chargeCode,
            ], $this->calculation->lines),
        ];
    }
}
