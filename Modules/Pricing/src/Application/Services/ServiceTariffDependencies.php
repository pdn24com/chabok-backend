<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\ServiceTariffDependenciesInterface;
use Modules\Pricing\Application\Dto\ResolvedServiceTariffDto;
use Modules\Pricing\Application\Dto\ServiceTariffResolutionDto;
use Modules\Pricing\Application\Enums\ServiceDependencyFailure;
use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;

final readonly class ServiceTariffDependencies implements ServiceTariffDependenciesInterface
{
    public function __construct(
        private ClockInterface $clock,
        private TariffRepositoryInterface $tariffRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
        private PricingChargeTypeRepositoryInterface $pricingChargeTypeRepository,
    ) {}

    public function ids(string $versionId): array
    {
        return $this->tariffRepository->serviceAttachmentIds($versionId);
    }

    public function replace(string $versionId, array $ids): void
    {
        $this->tariffRepository->deleteServiceAttachments($versionId);
        $attachments = [];
        foreach ($ids as $id) {
            $attachments[] = ['tariff_version_id' => $versionId, 'service_tariff_family_id' => $id];
        }
        if ($attachments !== []) {
            $this->tariffRepository->insertServiceAttachments($attachments);
        }
    }

    public function resolve(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localChargeTypeIds): array
    {
        $resolution = $this->inspect($ids, $hqId, $parentZoneVersionId, $asOf, $localChargeTypeIds);
        if ($resolution->failure !== null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, $resolution->failure->messageKey(), details: ['reason_code' => 'PRICING_SERVICE_DEPENDENCY_INVALID']);
        }

        return $resolution->tariffs;
    }

    public function inspect(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localChargeTypeIds): ServiceTariffResolutionDto
    {
        if (count(array_unique($ids)) !== count($ids)) {
            return new ServiceTariffResolutionDto(failure: ServiceDependencyFailure::DuplicateFamily);
        }
        if ($ids === []) {
            return new ServiceTariffResolutionDto;
        }
        $group = $parentZoneVersionId === null ? null : $this->pricingZoneRepository->zoneSetIdOfVersion($parentZoneVersionId);
        $charges = [];
        foreach ($this->pricingChargeTypeRepository->codesOf($localChargeTypeIds) as $code) {
            $charges[$this->chargeKey($code)] = true;
        }
        $effectiveAt = $asOf->utc();
        $families = $this->tariffRepository->serviceFamiliesWithEffectiveVersion($ids, $hqId, $effectiveAt);
        $tariffs = [];
        foreach ($ids as $id) {
            $family = $families->get($id);
            if ($family === null) {
                return new ServiceTariffResolutionDto(failure: ServiceDependencyFailure::FamilyUnavailable);
            }
            $version = $family->versions->first();
            if ($version === null) {
                return new ServiceTariffResolutionDto(failure: ServiceDependencyFailure::VersionUnpublished);
            }
            $serviceGroup = $version->zoneVersion?->pricing_zone_set_id;
            if ($serviceGroup !== null && $serviceGroup !== $group) {
                return new ServiceTariffResolutionDto(failure: ServiceDependencyFailure::ZoneGroupMismatch);
            }
            $code = $family->chargeType->code;
            $key = $this->chargeKey($code);
            if (isset($charges[$key])) {
                return new ServiceTariffResolutionDto(failure: ServiceDependencyFailure::DuplicateCharge);
            }
            $charges[$key] = true;
            $tariffs[] = new ResolvedServiceTariffDto($version, $code);
        }

        return new ServiceTariffResolutionDto($tariffs);
    }

    public function chargeKey(string $code): string
    {
        return $code === 'INSURANCE_FEE' ? 'INSURANCE' : $code;
    }

    public function successorFailure(string $kind, string $familyId, ?string $zoneVersionId): ?ServiceDependencyFailure
    {
        if ($kind !== 'SERVICE' || $zoneVersionId === null) {
            return null;
        }
        $group = $this->pricingZoneRepository->zoneSetIdOfVersion($zoneVersionId);
        $at = $this->clock->now();
        $incompatible = $this->tariffRepository->serviceFamilyBoundElsewhere($familyId, $group, $at);

        return $incompatible ? ServiceDependencyFailure::IncompatibleSuccessor : null;
    }
}
