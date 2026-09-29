<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Pricing\Application\Dto\PricingZoneVersionSearchDto;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

interface PricingZoneRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function createZoneSet(array $attributes): string;

    /** @param array<string, mixed> $attributes */
    public function createZoneSetVersion(array $attributes): string;

    public function versionVisible(?string $hqId, string $versionId): bool;

    public function lockTenantVersion(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord;

    public function findVisibleVersionDetail(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord;

    /** @param list<string> $versionIds @return Collection<int, PricingZoneSetVersionRecord> */
    public function visibleVersionDetails(?string $hqId, array $versionIds): Collection;

    public function findVisibleVersionWithZones(?string $hqId, string $versionId): ?PricingZoneSetVersionRecord;

    /** @return LengthAwarePaginator<PricingZoneSetRecord> */
    public function paginateZoneSets(?string $hqId, int $page, int $pageSize, string $search): LengthAwarePaginator;

    /** @return LengthAwarePaginator<PricingZoneSetVersionRecord> */
    public function paginateVersionReferences(?string $hqId, PricingZoneVersionSearchDto $filter): LengthAwarePaginator;

    /** Zone groups a tenant may use, each with the versions effective at a point in time. @return Collection<int, PricingZoneSetRecord> */
    public function publishedZoneSets(string $hqId, DateTimeInterface $at): Collection;

    public function findVisibleZoneSet(string $hqId, string $zoneSetId, bool $locking): ?PricingZoneSetRecord;

    /** @param list<string> $zoneSetIds @return Collection<string, PricingZoneSetRecord> */
    public function visibleZoneSetsWithCurrentVersion(string $hqId, array $zoneSetIds, DateTimeInterface $at): Collection;

    /** @return Collection<int, PricingZoneMemberRecord> */
    public function membersOfVersion(string $versionId): Collection;

    public function membersOfVersionByType(string $versionId, string $memberType): array;

    public function zoneSetIdOfVersion(?string $versionId): ?string;

    public function publishedVersionIdOfZoneSet(?string $zoneSetId, DateTimeInterface $asOf): ?string;

    /** Published versions effective at a point in time for the given Zone groups, newest first. @param list<string> $zoneSetIds @return Collection<int, PricingZoneSetVersionRecord> */
    public function publishedVersionsOfZoneSets(array $zoneSetIds, DateTimeInterface $asOf): Collection;

    /** @return list<string> */
    public function zoneIdsOfVersion(string $versionId): array;

    /** @return array<string, string> Zone codes keyed by Zone id. */
    public function zoneCodesOfVersion(?string $versionId): array;

    /** @return list<int|null> Explicit ranks of a version's Zones, in row order. */
    public function zoneRanksOfVersion(?string $versionId): array;

    /** @return array<string, string> Existing Zone ids keyed by code, so a rewrite can keep stable ids. */
    public function zoneIdsByCode(string $versionId): array;

    public function deleteZonesOfVersion(string $versionId): void;

    /** @param list<array<string, mixed>> $zones @param list<array<string, mixed>> $members Attribute sets; the repository applies each model's own casts. */
    public function insertZonesWithMembers(array $zones, array $members): void;

    /** Zones of several versions, grouped by version. @param list<string> $versionIds @return SupportCollection<string, Collection<int, \Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneRecord>> */
    public function zonesByVersion(array $versionIds): SupportCollection;
}
