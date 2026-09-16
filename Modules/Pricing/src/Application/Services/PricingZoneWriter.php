<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Domain\PostalRange;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingZoneWriter
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingZoneGuard $pricingZoneGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Pricing\Domain\PricingRulePolicy $pricingRulePolicy,
    )
    {
    }

    public function replaceZones(string $versionId, array $zones, ?string $sourceVersionId = null): void
    {
        $savedPostal = $this->pricing->savedPostalMembers($sourceVersionId ?? $versionId);
        foreach ($zones as &$zone) {
            foreach ($zone['members'] as &$member) {
                if ($member['member_type'] === 'POSTAL_RANGE') {
                    $from = PostalRange::normalize((string) ($member['reference_value'] ?? ''));
                    $to = PostalRange::normalize((string) ($member['range_end'] ?? ''));
                    $savedIndex = false;
                    foreach ($savedPostal as $index => $old) {
                        if ($old->pricing_zone_id === ($zone['pricing_zone_id'] ?? null) && $old->reference_value === $from && $old->range_end === $to) {
                            $savedIndex = $index;
                            break;
                        }
                    }
                    $unchanged = $savedIndex !== false;
                    if ($unchanged) {
                        unset($savedPostal[$savedIndex]);
                    }
                    if (!$unchanged && !PostalRange::valid($from, $to)) {
                        throw new ApiException(ApiErrorCode::ValidationError, 422, 'ابتدا و انتهای بازهٔ کدپستی باید دقیقاً ده رقم و به‌ترتیب باشند.', details: ['reason_code' => 'PRICING_POSTAL_RANGE_INVALID']);
                    }
                    $member['reference_value'] = $from;
                    $member['range_end'] = $to;
                }
            }
        }
        unset($zone, $member);
        $zones = $this->pricingZoneGuard->validatePolygons($zones);
        $codes = [];
        $ranks = [];
        foreach ($zones as $zone) {
            $code = mb_strtoupper($zone['code']);
            $rank = $zone['rank'] ?? null;
            if (isset($codes[$code]) || $rank !== null && ($rank < 1 || (int) $rank != $rank || isset($ranks[(int) $rank]))) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone codes and explicit ranks must be unique within the version.');
            }
            $codes[$code] = true;
            if ($rank !== null) {
                $ranks[(int) $rank] = true;
            }
        }
        $existing = $this->pricing->zoneIdsByCode($versionId);
        $this->pricing->deleteZones($versionId);
        foreach ($zones as $zone) {
            $requestedId = $zone['pricing_zone_id'] ?? null;
            $zoneId = in_array($requestedId, $existing, true) ? $requestedId : $existing[mb_strtoupper($zone['code'])] ?? $this->identifiers->uuid();
            $this->pricing->insertZone([
                'pricing_zone_id' => $zoneId,
                'zone_set_version_id' => $versionId,
                'code' => mb_strtoupper($zone['code']),
                'title' => $zone['title'],
                'remote_area' => $zone['remote_area'] ?? false,
                'rank' => $zone['rank'] ?? null,
            ]);
            foreach ((array) ($zone['members'] ?? []) as $member) {
                $type = (string) $member['member_type'];
                $city = $type === 'CITY' ? $this->pricingZoneGuard->canonicalCityMember($member) : null;
                $cityId = $city['city_id'] ?? null;
                $provinceId = $type === 'PROVINCE' ? (string) ($member['province_id'] ?? '') : null;
                if ($type === 'PROVINCE' && ($provinceId === '' || !$this->pricing->activeProvince($provinceId))) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Zone member province is inactive or invalid.');
                }
                $reference = $cityId ?? $provinceId ?? (string) ($member['reference_value'] ?? '');
                $this->pricing->insertZoneMember([
                    'zone_member_id' => $this->identifiers->uuid(),
                    'pricing_zone_id' => $zoneId,
                    'member_type' => $type,
                    'reference_value' => $reference,
                    'city_id' => $cityId,
                    'province_id' => $provinceId,
                    'range_end' => $member['range_end'] ?? null,
                    'geometry' => isset($member['geometry']) ? json_encode($member['geometry'], JSON_THROW_ON_ERROR) : null,
                    'precedence' => $this->pricingRulePolicy->memberPrecedence($type),
                ]);
            }
        }
    }
}
