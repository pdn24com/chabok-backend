<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingZoneGuard
{
    public function __construct(
        private \Modules\Geography\Application\PolygonGeometry $polygons,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Geography\Domain\PersianSearchNormalizer $normalizer,
    )
    {
    }

    public function validatePolygons(array $zones): array
    {
        $polygons = [];
        foreach ($zones as $zi => &$zone) {
            foreach ($zone['members'] as &$member) {
                if ($member['member_type'] !== 'POLYGON') {
                    unset($member['geometry']);
                    continue;
                }
                $member['geometry'] = $this->polygons->normalize((array) ($member['geometry'] ?? []));
                foreach ($polygons as [$otherZone, $geometry]) {
                    if ($otherZone !== $zi && $this->polygons->intersects($geometry, $member['geometry'])) {
                        throw new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'محدوده با چندضلعی منطقهٔ دیگری تداخل دارد؛ پیش از ذخیره مرزها را اصلاح کنید.', details: ['reason_code' => 'PRICING_POLYGON_OVERLAP']);
                    }
                }
                $polygons[] = [$zi, $member['geometry']];
            }
        }
        unset($zone, $member);
        return $zones;
    }

    public function canonicalCityMember(array $member): array
    {
        $cityId = (string) ($member['city_id'] ?? '');
        if ($cityId !== '') {
            $city = $this->pricing->activeCity($cityId);
        } else {
            $legacyName = trim((string) ($member['reference_value'] ?? ''));
            $matches = $this->pricing->citiesByName($this->normalizer->normalize($legacyName));
            if (count($matches) !== 1) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Legacy CITY member cannot be mapped unambiguously to canonical Geography.');
            }
            $city = $matches[0];
        }
        if ($city === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone member city is inactive or invalid.');
        }
        $canonicalProvinceId = (string) $city->province_id;
        $providedProvinceId = (string) ($member['province_id'] ?? '');
        if ($providedProvinceId !== '' && $providedProvinceId !== $canonicalProvinceId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone member province does not match the selected City.', fieldErrors: ['province_id' => ['Province must match the selected City.']]);
        }
        return ['city_id' => (string) $city->city_id, 'province_id' => $canonicalProvinceId];
    }
}
