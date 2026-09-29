<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Application\Support\ApiMessage;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Geography\Application\Contracts\PolygonGeometryInterface;
use Modules\Geography\Application\Repositories\CityRepositoryInterface;
use Modules\Geography\Application\Repositories\ProvinceRepositoryInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\Enums\GeometryFailure;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use Modules\Pricing\Application\Contracts\PricingZoneGuardInterface;
use Modules\Pricing\Application\Dto\PolygonValidationFailureDto;
use Modules\Pricing\Domain\Enums\ZoneMemberType;
use Modules\Pricing\Domain\Exceptions\InvalidPricingZone;

final readonly class PricingZoneGuard implements PricingZoneGuardInterface
{
    public function __construct(
        private PolygonGeometryInterface $polygonGeometry,
        private PersianSearchNormalizer $normalizer,
        private CityRepositoryInterface $cityRepository,
        private ProvinceRepositoryInterface $provinceRepository,
    ) {}

    public function validatePolygons(array $zones): array
    {
        $failure = $this->inspectPolygons($zones);
        if ($failure !== null) {
            throw new InvalidPricingZone($failure->code, $failure->message, $failure->reasonCode);
        }

        return $zones;
    }

    public function inspectPolygons(array $zones): ?PolygonValidationFailureDto
    {
        $polygonsByZone = [];
        foreach ($zones as $zoneIndex => $zone) {
            foreach ($zone->members as $member) {
                if ($member->type !== ZoneMemberType::POLYGON) {
                    $member->geometry = null;

                    continue;
                }
                $geometry = $this->polygonGeometry->inspect($member->geometry ?? []);
                if ($geometry instanceof GeometryFailure) {
                    return new PolygonValidationFailureDto(ApiErrorCode::ValidationError, ApiMessage::translate($geometry->messageKey()),
                        $geometry === GeometryFailure::INVALID_TOPOLOGY ? 'PRICING_POLYGON_INVALID' : null);
                }
                $member->geometry = GeoJson::serialize($geometry);
                foreach ($polygonsByZone as $otherZone => $otherPolygons) {
                    if ($otherZone === $zoneIndex) {
                        continue;
                    }
                    foreach ($otherPolygons as $otherPolygon) {
                        if ($this->polygonGeometry->intersects($otherPolygon, $geometry)) {
                            return new PolygonValidationFailureDto(ApiErrorCode::PricingZoneAmbiguous, 'محدوده با چندضلعی منطقهٔ دیگری تداخل دارد؛ پیش از ذخیره مرزها را اصلاح کنید.', 'PRICING_POLYGON_OVERLAP');
                        }
                    }
                }
                $polygonsByZone[$zoneIndex][] = $geometry;
            }
        }

        return null;
    }

    public function resolveGeography(array $zones): void
    {
        $cityIds = [];
        $cityNames = [];
        $provinceIds = [];
        foreach ($zones as $zone) {
            foreach ($zone->members as $member) {
                if ($member->type === ZoneMemberType::CITY) {
                    if ($member->cityId !== null && $member->cityId !== '') {
                        $cityIds[] = $member->cityId;
                    } else {
                        $cityNames[] = $this->normalizer->normalize(trim($member->reference));
                    }
                }
                if ($member->type === ZoneMemberType::PROVINCE) {
                    $provinceIds[] = $member->provinceId;
                }
            }
        }
        $cities = $this->cityRepository->activeByIdsOrNormalizedNames($cityIds, $cityNames);
        $citiesById = $cities->keyBy('city_id');
        $citiesByName = $cities->groupBy('normalized_name');
        $activeProvinces = $this->provinceRepository->activeIds($provinceIds);
        foreach ($zones as $zone) {
            foreach ($zone->members as $member) {
                if ($member->type === ZoneMemberType::PROVINCE && ! in_array($member->provinceId, $activeProvinces, true)) {
                    throw new InvalidPricingZone(ApiErrorCode::ValidationError, 'pricing.zone_member_province_is_inactive_or_invalid');
                }
                if ($member->type !== ZoneMemberType::CITY) {
                    continue;
                }
                if ($member->cityId !== null && $member->cityId !== '') {
                    $city = $citiesById->get($member->cityId);
                } else {
                    $matches = $citiesByName->get($this->normalizer->normalize(trim($member->reference)));
                    if ($matches === null || $matches->count() !== 1) {
                        throw new InvalidPricingZone(ApiErrorCode::ValidationError, 'pricing.legacy_city_member_is_ambiguous');
                    }
                    $city = $matches->first();
                }
                if ($city === null) {
                    throw new InvalidPricingZone(ApiErrorCode::ValidationError, 'pricing.zone_member_city_is_inactive_or_invalid');
                }
                if ($member->provinceId !== null && $member->provinceId !== '' && $member->provinceId !== $city->province_id) {
                    throw new InvalidPricingZone(ApiErrorCode::ValidationError, 'pricing.zone_member_province_does_not_match_city', fieldErrors: ['province_id' => ['pricing.province_must_match_selected_city']]);
                }
                $member->cityId = $city->city_id;
            }
        }
    }
}
