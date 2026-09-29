<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use DateTimeImmutable;
use Modules\Foundation\Domain\Enums\Currency;
use Modules\Pricing\Application\Mappers\FreightMatrixDraftInput;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Enums\PricingPurpose;
use Modules\Pricing\Domain\Enums\TariffKind;
use Modules\Pricing\Domain\Enums\TariffScope;
use Modules\Pricing\Domain\Enums\WeightRoundingMode;
use Modules\Pricing\Domain\Enums\ZonePolicy;
use Modules\Pricing\Domain\Exceptions\InvalidPricingInput;

/** Working tariff definition; matrix drafts preserve the legacy tail/band representation for storage. */
final class TariffDraftDto
{
    /** @param list<FreightMatrixDraftDto> $freightMatrices @param list<PricingRateRuleDraftDto> $rules @param list<string> $serviceTariffFamilyIds */
    public function __construct(public string $code = '', public ?string $title = null, public ?PricingPurpose $purpose = null,
        public string $currency = Currency::Irr->value, public ?TariffKind $kind = TariffKind::Freight, public ?TariffScope $scopeType = TariffScope::Tenant,
        public ?string $scopeValue = null, public int $priority = 100, public ?string $serviceChargeTypeId = null,
        public ?string $zoneSetVersionId = null, public ?ZonePolicy $zonePolicy = ZonePolicy::DIRECTIONAL,
        public ?PricingBasis $matrixBasis = null, public bool $isDefault = false, public array $freightMatrices = [],
        public array $rules = [], public array $serviceTariffFamilyIds = [], public ?DateTimeImmutable $validFrom = null,
        public ?DateTimeImmutable $validTo = null, public string $volumetricDivisor = '5000', public string $weightRoundingStepKg = '0.5',
        public WeightRoundingMode $roundingMode = WeightRoundingMode::StepUp, public ?int $expectedVersion = null) {}

    public static function fromInput(array $input): self
    {
        return new self(code: (string) ($input['code'] ?? ''), title: isset($input['title']) ? trim($input['title']) : null,
            purpose: isset($input['purpose']) ? PricingPurpose::from($input['purpose']) : null, currency: $input['currency'] ?? Currency::Irr->value,
            kind: TariffKind::tryFrom($input['tariff_kind'] ?? 'FREIGHT'), scopeType: TariffScope::tryFrom($input['scope_type'] ?? 'TENANT'),
            scopeValue: $input['scope_value'] ?? null, priority: (int) ($input['priority'] ?? 100),
            serviceChargeTypeId: $input['service_charge_type_id'] ?? null, zoneSetVersionId: $input['zone_set_version_id'] ?? null,
            zonePolicy: ZonePolicy::tryFrom($input['zone_policy'] ?? 'DIRECTIONAL'),
            matrixBasis: isset($input['matrix_basis']) ? (PricingBasis::tryFrom($input['matrix_basis']) ?? throw new InvalidPricingInput('pricing.matrix_basis_is_invalid')) : null,
            isDefault: (bool) ($input['is_default'] ?? false), freightMatrices: FreightMatrixDraftInput::many($input['freight_matrices'] ?? []),
            rules: array_map(PricingRateRuleDraftDto::fromInput(...), $input['rules'] ?? []), serviceTariffFamilyIds: $input['service_tariff_family_ids'] ?? [],
            validFrom: empty($input['valid_from']) ? null : new DateTimeImmutable($input['valid_from']),
            validTo: empty($input['valid_to']) ? null : new DateTimeImmutable($input['valid_to']),
            volumetricDivisor: (string) ($input['volumetric_divisor'] ?? 5000), weightRoundingStepKg: (string) ($input['weight_rounding_step_kg'] ?? 0.5),
            roundingMode: WeightRoundingMode::from($input['rounding_mode'] ?? 'STEP_UP'),
            expectedVersion: isset($input['expected_version']) ? (int) $input['expected_version'] : null);
    }
}
