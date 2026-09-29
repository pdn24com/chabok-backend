<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Organization\Application\Repositories\AreaRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneSetRepositoryInterface;
use Modules\ServiceCatalog\Application\Contracts\OfferingReferenceGuardInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

final readonly class OfferingReferenceGuard implements OfferingReferenceGuardInterface
{
    public function __construct(
        private ProvinceRepositoryInterface $provinceRepository,
        private CityRepositoryInterface $cityRepository,
        private AreaRepositoryInterface $areaRepository,
        private PricingZoneSetRepositoryInterface $pricingZoneSetRepository,
    ) {}

    public function assertOfferingReferences(CatalogDraftDto $input, ?string $hqId): void
    {
        foreach ($input->availabilityBindings as $binding) {
            $scopeType = (string) ($binding->scopeType ?? '');
            if (! in_array($scopeType, ['PLATFORM', 'TENANT', 'CHANNEL'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.selected_availability_scope_has_no_authoritative_reference');
            }
            if ($scopeType === 'CHANNEL' && ! in_array((string) ($binding->scopeValue ?? ''), ['BRANCH', 'VENDOR', 'DRIVER', 'HQ', 'API', 'TRACKING', 'LEGACY'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.availability_channel_is_invalid');
            }
        }
        $references = $input->coverageReferences;
        $ids = [];
        foreach ($references as $reference) {
            $ids[$reference->referenceType][] = $reference->referenceValue;
        }
        $provinces = empty($ids['PROVINCE']) ? [] : $this->provinceRepository->activeIds($ids['PROVINCE']);
        $cities = empty($ids['CITY']) ? [] : $this->cityRepository->activeIds($ids['CITY']);
        $areas = $hqId === null || empty($ids['OPERATIONAL_AREA']) ? [] : $this->areaRepository->activeIdsAmong($hqId, $ids['OPERATIONAL_AREA']);
        $zoneSets = empty($ids['PRICING_ZONE_SET']) ? [] : $this->pricingZoneSetRepository->visibleVersionIdsAmong($ids['PRICING_ZONE_SET'], $hqId);
        foreach ($references as $reference) {
            $type = (string) ($reference->referenceType ?? '');
            $value = (string) ($reference->referenceValue ?? '');
            $valid = match ($type) {
                'COUNTRY' => $value === 'IR',
                'PROVINCE' => in_array($value, $provinces, true),
                'CITY' => in_array($value, $cities, true),
                'OPERATIONAL_AREA' => $hqId !== null && in_array($value, $areas, true),
                'PRICING_ZONE_SET' => in_array($value, $zoneSets, true),
                'POSTAL_RANGE' => preg_match('/^\d{10}$/', $value) === 1 && preg_match('/^\d{10}$/', (string) ($reference->secondaryReferenceValue ?? '')) === 1 && strcmp($value, (string) $reference->secondaryReferenceValue) <= 0,
                default => false,
            };
            if (! $valid) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'servicecatalog.coverage_reference_is_invalid_outside_current_tenant');
            }
        }
    }
}
