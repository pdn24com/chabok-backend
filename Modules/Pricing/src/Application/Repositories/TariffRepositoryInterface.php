<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

interface TariffRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function createFamily(array $attributes): string;

    /** @param array<string, mixed> $attributes */
    public function createVersion(array $attributes): string;

    public function lockTenantVersion(?string $hqId, string $versionId): ?TariffVersionRecord;

    /** The draft a simulation runs against, with its family, locked so the run sees one consistent definition. */
    public function lockTenantDraftWithFamily(?string $hqId, string $versionId): ?TariffVersionRecord;

    public function findVisibleVersionDetail(?string $hqId, string $versionId): ?TariffVersionRecord;

    /** @param list<string> $versionIds @return Collection<int, TariffVersionRecord> */
    public function visibleVersionDetails(?string $hqId, array $versionIds): Collection;

    /** @return LengthAwarePaginator<TariffFamilyRecord> */
    public function paginateFamilies(?string $hqId, int $page, int $pageSize, string $search): LengthAwarePaginator;

    /** The one Tariff version a quote must use: published, effective, and highest precedence by family scope. @param list<string> $offeringReferences */
    public function findEligibleFreightVersion(string $hqId, array $offeringReferences, DateTimeInterface $asOf): ?TariffVersionRecord;

    /** Service families a tariff may attach to, with their effective version and Zone group. @param list<string> $familyIds @return Collection<string, TariffFamilyRecord> */
    public function serviceFamiliesWithEffectiveVersion(array $familyIds, ?string $hqId, DateTimeInterface $effectiveAt): Collection;

    /** Published service families a caller may reference, with their effective versions. @return Collection<int, TariffFamilyRecord> */
    public function referenceableServiceFamilies(?string $hqId, DateTimeInterface $at): Collection;

    /** Another tenant default already covering any of the same Offerings, which is what stops two defaults. @param list<string> $offeringIds */
    public function hasCompetingDefault(?string $hqId, string $tariffFamilyId, array $offeringIds, mixed $validFrom, mixed $validTo): bool;

    /** Offering identities the rules of one Tariff version point at. @return list<string> */
    public function offeringIdsOfVersionRules(string $versionId): array;

    public function deleteRules(string $versionId): void;

    /** @param list<array<string, mixed>> $rows Attribute sets; the repository applies the model's own casts. */
    public function insertRules(array $rows): void;

    /** Rules of one Tariff that can apply to the given catalogue selection, with their charge types and Zones. @param list<string> $offeringReferences @param list<string> $optionReferences @return Collection<int, TariffRateRuleRecord> */
    public function applicableRules(string $versionId, array $offeringReferences, array $optionReferences): Collection;

    /** Rules of dependency Tariffs, grouped by version. @param list<string> $versionIds @return SupportCollection<string, Collection<int, TariffRateRuleRecord>> */
    public function rulesByVersion(array $versionIds): SupportCollection;

    /** @return list<string> */
    public function serviceAttachmentIds(string $versionId): array;

    public function deleteServiceAttachments(string $versionId): void;

    /** @param list<array<string, mixed>> $rows */
    public function insertServiceAttachments(array $rows): void;

    /** Whether a service family is already attached to a live Tariff bound to a different Zone group. */
    public function serviceFamilyBoundElsewhere(string $serviceFamilyId, ?string $zoneSetId, DateTimeInterface $at): bool;
}
