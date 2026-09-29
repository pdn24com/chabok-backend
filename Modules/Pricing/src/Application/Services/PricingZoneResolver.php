<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\Geography\Application\Contracts\PolygonGeometryInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\Support\PersianSearchNormalizer;
use Modules\Organization\Application\Repositories\TenantRepositoryInterface;
use Modules\Pricing\Application\Contracts\PricingZoneResolverInterface;
use Modules\Pricing\Application\Dto\PricingLaneDto;
use Modules\Pricing\Application\Dto\ZoneResolutionDto;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Domain\Enums\ZoneMatchFailure;
use Modules\Pricing\Domain\Enums\ZoneMemberType;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneGroupDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneMatchDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneSummaryDto;

final readonly class PricingZoneResolver implements PricingZoneResolverInterface
{
    public function __construct(
        private ClockInterface $clock,
        private PersianSearchNormalizer $normalizer,
        private PolygonGeometryInterface $polygonGeometry,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
        private TenantRepositoryInterface $tenantRepository,
    ) {}

    public function groups(string $hqId): array
    {
        $at = $this->clock->now();
        $groups = $this->pricingZoneRepository->publishedZoneSets($hqId, $at);

        return $groups->map(fn (PricingZoneSetRecord $group): CommitmentZoneGroupDto => $this->groupData($group, $group->versions->first()))->all();
    }

    public function group(
        string $hqId,
        string $groupId,
        bool $locking = false,
    ): CommitmentZoneGroupDto {
        if ($locking) {
            $this->tenantRepository->lockIdentity($hqId);
        }
        $group = $this->pricingZoneRepository->findVisibleZoneSet($hqId, $groupId, $locking);
        if ($group === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_is_unavailable');
        }
        $v = $group->versions()->publishedAt($this->clock->now())->orderByDesc('version_number')->first();
        if (! $v) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_has_no_published_version');
        }

        return $this->groupData($group, $v->load('zones'));
    }

    /** @param list<string> $groupIds */
    public function destinations(string $hqId, array $groupIds, CoverageAddress $address): CommitmentDestinationsDto
    {
        if ($groupIds === []) {
            return new CommitmentDestinationsDto([]);
        }
        $at = $this->clock->now();
        $groups = $this->pricingZoneRepository->visibleZoneSetsWithCurrentVersion($hqId, $groupIds, $at);
        $allMembers = new Collection;
        foreach ($groups as $group) {
            foreach ($group->versions->first()?->zones ?? [] as $zone) {
                foreach ($zone->members as $member) {
                    $allMembers->push($member);
                }
            }
        }
        $polygonMatches = $this->polygonMatches($allMembers, $address);
        $destinations = [];
        foreach (array_unique($groupIds) as $groupId) {
            $group = $groups->get($groupId);
            if ($group === null) {
                $destinations[$groupId] = new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_is_unavailable');

                continue;
            }
            $version = $group->versions->first();
            if ($version === null) {
                $destinations[$groupId] = new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.zone_set_has_no_published_version');

                continue;
            }
            $members = new Collection;
            foreach ($version->zones as $zone) {
                foreach ($zone->members as $member) {
                    $member->setRelation('zone', $zone);
                    $members->push($member);
                }
            }
            // Use the same ordering as the single-version lookup for tied members of one zone.
            $members = $members->sortBy('id')->values();
            $resolution = $this->matchZone($members, $address, $polygonMatches);
            if ($resolution instanceof ZoneMatchFailure && $resolution !== ZoneMatchFailure::Unresolved) {
                $destinations[$groupId] = $this->matchException($resolution, 'destination');

                continue;
            }
            $match = $resolution === ZoneMatchFailure::Unresolved ? null : new CommitmentZoneMatchDto(
                $resolution->zone->pricing_zone_id, $resolution->zone->code, $resolution->zone->title,
                $resolution->zone->rank === null ? null : (int) $resolution->zone->rank,
                (bool) $resolution->zone->remote_area, $resolution->member->zone_member_id,
                $resolution->member->member_type, $resolution->precedence);
            $destinations[$groupId] = new CommitmentDestinationDto($groupId, $version->zone_set_version_id, $match);
        }

        return new CommitmentDestinationsDto($destinations);
    }

    public function resolveZone(string $versionId, CoverageAddress $party, string $partyName): ZoneResolutionDto
    {
        return $this->requireMatch($this->members($versionId), $party, $partyName);
    }

    public function resolveLane(string $versionId, CoverageAddress $origin, CoverageAddress $destination): PricingLaneDto
    {
        $members = $this->members($versionId);

        return new PricingLaneDto($this->requireMatch($members, $origin, 'sender'), $this->requireMatch($members, $destination, 'receiver'));
    }

    public function resolveEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): string
    {
        $identityId = $this->pricingZoneRepository->zoneSetIdOfVersion($configuredVersionId);
        if ($identityId === null) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.pricing_zone_set_version_could_not_be', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }
        $versionId = $this->pricingZoneRepository->publishedVersionIdOfZoneSet($identityId, $asOf);
        if ($versionId === null) {
            throw new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.no_effective_published_pricing_zone_set_version', details: ['reason_code' => 'PRICING_ZONE_VERSION_UNAVAILABLE']);
        }

        return $versionId;
    }

    public function findEffectiveZoneSetVersion(string $configuredVersionId, CarbonImmutable $asOf): ?string
    {
        $identityId = $this->pricingZoneRepository->zoneSetIdOfVersion($configuredVersionId);
        if ($identityId === null) {
            return null;
        }

        return $this->pricingZoneRepository->publishedVersionIdOfZoneSet($identityId, $asOf);
    }

    /** @return Collection<int, PricingZoneMemberRecord> */
    private function members(string $versionId): Collection
    {
        return $this->pricingZoneRepository->membersOfVersion($versionId);
    }

    /** @param Collection<int, PricingZoneMemberRecord> $members */
    private function matchZone(Collection $members, CoverageAddress $party, ?array $polygonMatches = null): ZoneResolutionDto|ZoneMatchFailure
    {
        $winners = [];
        $top = -1;
        if ($members->contains('member_type', 'POLYGON')) {
            foreach (['latitude' => 90, 'longitude' => 180] as $coordinate => $limit) {
                $value = $coordinate === 'latitude' ? $party->latitude : $party->longitude;
                if (! is_numeric($value) || ! is_finite((float) $value) || abs((float) $value) > $limit) {
                    return $coordinate === 'latitude' ? ZoneMatchFailure::LatitudeRequired : ZoneMatchFailure::LongitudeRequired;
                }
            }
        }
        $polygonMatches ??= $this->polygonMatches($members, $party);
        foreach ($members as $m) {
            $matchesMember = match ($m->member_type) {
                'EXPLICIT_OVERRIDE' => ($party->zoneOverride ?? null) === $m->reference_value,
                'POSTAL_RANGE' => isset($party->postalCode) && strcmp((string) $party->postalCode, (string) $m->reference_value) >= 0 && strcmp((string) $party->postalCode, (string) $m->range_end) <= 0,
                'CITY' => $m->city_id !== null ? ($party->cityId ?? null) === $m->city_id : isset($party->city) && $this->normalizer->normalize((string) $party->city) === $this->normalizer->normalize((string) $m->reference_value),
                'POLYGON' => $polygonMatches[$m->zone_member_id],
                'PROVINCE' => $m->province_id !== null ? ($party->provinceId ?? null) === $m->province_id : isset($party->state) && $this->normalizer->normalize((string) $party->state) === $this->normalizer->normalize((string) $m->reference_value),
                default => false,
            };
            if ($matchesMember) {
                $precedence = ZoneMemberType::tryFrom($m->member_type)?->precedence() ?? 0;
                if ($precedence > $top) {
                    $top = $precedence;
                    $winners = [$m];
                } elseif ($precedence === $top) {
                    $winners[] = $m;
                }
            }
        }
        if ($winners === []) {
            return ZoneMatchFailure::Unresolved;
        }
        if (count(array_unique(array_map(fn ($m) => $m->pricing_zone_id, $winners))) > 1) {
            return ZoneMatchFailure::Ambiguous;
        }
        $winner = $winners[0];

        return new ZoneResolutionDto($winner->zone, $winner, $top);
    }

    /** @param Collection<int, PricingZoneMemberRecord> $members */
    private function requireMatch(Collection $members, CoverageAddress $party, string $partyName): ZoneResolutionDto
    {
        $resolution = $this->matchZone($members, $party);
        if ($resolution instanceof ZoneMatchFailure) {
            throw $this->matchException($resolution, $partyName);
        }

        return $resolution;
    }

    private function matchException(ZoneMatchFailure $failure, string $partyName): ApiException
    {
        if ($failure === ZoneMatchFailure::Unresolved) {
            return new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.pricing_zone_could_not_be_resolved', details: ['reason_code' => 'PRICING_ZONE_UNRESOLVED']);
        }
        if ($failure === ZoneMatchFailure::Ambiguous) {
            return new ApiException(ApiErrorCode::PricingZoneAmbiguous, 422, 'pricing.pricing_zone_is_ambiguous', details: ['reason_code' => 'PRICING_ZONE_AMBIGUOUS']);
        }
        $coordinate = $failure === ZoneMatchFailure::LatitudeRequired ? 'latitude' : 'longitude';

        return new ApiException(ApiErrorCode::PricingZoneUnresolved, 422, 'pricing.tariff_requires_exact_coordinates', fieldErrors: ["{$partyName}.{$coordinate}" => ['pricing.exact_address_coordinates_are_required']], details: ['reason_code' => 'PRICING_COORDINATES_REQUIRED', 'party' => $partyName]);
    }

    /** @param Collection<int, PricingZoneMemberRecord> $members @return array<string, bool> */
    private function polygonMatches(Collection $members, CoverageAddress $address): array
    {
        // Coordinate failures are returned for the affected group by matchZone.
        if (! is_numeric($address->latitude) || ! is_finite((float) $address->latitude) || abs((float) $address->latitude) > 90
            || ! is_numeric($address->longitude) || ! is_finite((float) $address->longitude) || abs((float) $address->longitude) > 180) {
            return [];
        }
        $geometries = [];
        foreach ($members as $member) {
            if ($member->member_type === ZoneMemberType::POLYGON->value) {
                $geometries[$member->zone_member_id] = GeoJson::geometry($member->geometry);
            }
        }

        return $geometries === [] ? [] : $this->polygonGeometry->containsMany($geometries, (float) $address->latitude, (float) $address->longitude);
    }

    private function groupData(PricingZoneSetRecord $group, PricingZoneSetVersionRecord $version): CommitmentZoneGroupDto
    {
        return new CommitmentZoneGroupDto($group->pricing_zone_set_id, $version->zone_set_version_id, $group->code, $group->title,
            $version->zones->map(fn ($zone): CommitmentZoneSummaryDto => new CommitmentZoneSummaryDto($zone->code, $zone->title))->all());
    }
}
