<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingDraftPreparationInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Contracts\TariffMatrixCompilerInterface;
use Modules\Pricing\Application\Dto\PricingZoneColumnDto;
use Modules\Pricing\Application\Dto\TariffDraftDto;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\Enums\TariffKind;

final readonly class PricingDraftPreparation implements PricingDraftPreparationInterface
{
    public function __construct(
        private PricingReaderInterface $pricingReader,
        private TariffMatrixCompilerInterface $tariffMatrixCompiler,
        private ServiceTariffDependenciesInterface $serviceTariffDependencies,
        private ClockInterface $clock,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function prepareTariffDraft(AuthenticatedPrincipal $actor, TariffDraftDto $input): TariffDraftDto
    {
        $kind = $input->kind;
        if ($kind === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.tariff_kind_is_invalid');
        }
        if ($kind === TariffKind::Freight && empty($input->zoneSetVersionId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_is_required');
        }
        if ($kind === TariffKind::Freight) {
            $input->matrixBasis = PricingBasis::BILLABLE_WEIGHT;
            $input->serviceChargeTypeId = null;
        }
        $zones = [];
        if ($input->zoneSetVersionId !== null) {
            foreach ($this->pricingReader->zoneVersion($actor, $input->zoneSetVersionId)->zones as $zone) {
                $zones[] = new PricingZoneColumnDto($zone->pricing_zone_id, $zone->rank);
            }
        }
        $prepared = $this->tariffMatrixCompiler->prepare($input, $zones, $actor->hqId);
        $this->serviceTariffDependencies->resolve($input->serviceTariffFamilyIds, $actor->hqId, $input->zoneSetVersionId, CarbonImmutable::instance($input->validFrom ?? $this->clock->now())->max(CarbonImmutable::instance($this->clock->now())), array_map(static fn ($rule): string => $rule->chargeTypeId, $prepared->rules));

        return $prepared;
    }

    public function assertTariffReferences(AuthenticatedPrincipal $actor, TariffDraftDto $input): void
    {
        $zoneSetVersionId = (string) ($input->zoneSetVersionId);
        $visible = $this->pricingZoneRepository->versionVisible($actor->hqId, $zoneSetVersionId);
        if (! $visible && ! ($input->kind === TariffKind::Service && $zoneSetVersionId === '')) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $zoneIds = $this->pricingZoneRepository->zoneIdsOfVersion($zoneSetVersionId);
        foreach ($input->rules as $rule) {
            foreach (['origin_zone_id' => $rule->originZoneId, 'destination_zone_id' => $rule->destinationZoneId] as $field => $zoneId) {
                if ($zoneId !== null && $zoneId !== '' && ! in_array((string) $zoneId, $zoneIds, true)) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.tariff_rule_references_zone_outside_selected_zone', details: ['field' => "rules.{$field}"]);
                }
            }
        }
    }
}
