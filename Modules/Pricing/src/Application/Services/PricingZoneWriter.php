<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingZoneGuardInterface;
use Modules\Pricing\Application\Contracts\PricingZoneWriterInterface;
use Modules\Pricing\Application\Dto\PricingZoneDraftDto;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Domain\Enums\ZoneMemberType;
use Modules\Pricing\Domain\Support\PostalRange;

final readonly class PricingZoneWriter implements PricingZoneWriterInterface
{
    public function __construct(
        private PricingZoneGuardInterface $pricingZoneGuard,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function replaceZones(
        string $versionId,
        array $zones,
        ?string $sourceVersionId = null,
    ): void {
        $this->normalizePostalMembers($zones, $sourceVersionId ?? $versionId);
        $zones = $this->pricingZoneGuard->validatePolygons($zones);
        $codes = [];
        $ranks = [];
        foreach ($zones as $zone) {
            $code = mb_strtoupper($zone->code);
            $rank = $zone->rank;
            if (isset($codes[$code]) || $rank !== null && ($rank < 1 || (int) $rank != $rank || isset($ranks[(int) $rank]))) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_codes_explicit_ranks_must_be_unique');
            }
            $codes[$code] = true;
            if ($rank !== null) {
                $ranks[(int) $rank] = true;
            }
        }
        $this->pricingZoneGuard->resolveGeography($zones);
        $existing = $this->pricingZoneRepository->zoneIdsByCode($versionId);
        $this->pricingZoneRepository->deleteZonesOfVersion($versionId);
        $zoneRows = [];
        $memberRows = [];
        foreach ($zones as $zone) {
            $requestedId = $zone->id;
            $zoneId = in_array($requestedId, $existing, true) ? $requestedId : $existing[mb_strtoupper($zone->code)] ?? null;
            $zoneRows[] = [
                'pricing_zone_id' => $zoneId,
                'zone_set_version_id' => $versionId,
                'code' => mb_strtoupper($zone->code),
                'title' => $zone->title,
                'remote_area' => $zone->remoteArea,
                'rank' => $zone->rank,
            ];
            foreach ($zone->members as $member) {
                $type = $member->type;
                $cityId = $type === ZoneMemberType::CITY ? $member->cityId : null;
                $provinceId = $type === ZoneMemberType::PROVINCE ? $member->provinceId : null;
                $reference = $cityId ?? $provinceId ?? $member->reference;
                $memberRows[] = [

                    'pricing_zone_id' => $zoneId,
                    '_zone_code' => mb_strtoupper($zone->code),
                    '_zone_version_id' => $versionId,
                    'member_type' => $type->value,
                    'reference_value' => $reference,
                    'city_id' => $cityId,
                    'province_id' => $provinceId,
                    'range_end' => $member->rangeEnd,
                    'geometry' => $member->geometry,
                    'precedence' => $type->precedence(),
                ];
            }
        }
        $this->pricingZoneRepository->insertZonesWithMembers($zoneRows, $memberRows);
    }

    /** @param list<PricingZoneDraftDto> $zones */
    private function normalizePostalMembers(array $zones, string $sourceVersionId): void
    {
        $savedPostal = $this->pricingZoneRepository->membersOfVersionByType($sourceVersionId, ZoneMemberType::POSTAL_RANGE->value);
        $remaining = [];
        foreach ($savedPostal as $saved) {
            $remaining[$saved->pricing_zone_id][$saved->reference_value][$saved->range_end] = ($remaining[$saved->pricing_zone_id][$saved->reference_value][$saved->range_end] ?? 0) + 1;
        }
        foreach ($zones as $zone) {
            foreach ($zone->members as $member) {
                if ($member->type === ZoneMemberType::POSTAL_RANGE) {
                    $from = PostalRange::normalize($member->reference);
                    $to = PostalRange::normalize((string) ($member->rangeEnd ?? ''));
                    $unchanged = ($remaining[$zone->id][$from][$to] ?? 0) > 0;
                    if ($unchanged) {
                        $remaining[$zone->id][$from][$to]--;
                    }
                    if (! $unchanged && ! PostalRange::valid($from, $to)) {
                        throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.postal_range_is_invalid', details: ['reason_code' => 'PRICING_POSTAL_RANGE_INVALID']);
                    }
                    $member->reference = $from;
                    $member->rangeEnd = $to;
                }
            }
        }
    }
}
