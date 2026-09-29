<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolverInterface;
use Modules\ServiceCatalog\Application\Contracts\CurrentCatalogInterface;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto;
use Modules\ServiceCatalog\Application\Dto\QuoteCatalogReferencesDto;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\CatalogDraftDocument;
use Modules\ServiceCatalog\Application\Serialization\ScheduleInputDocument;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

/** Resolve legacy revision references through their immutable stable identity. */
final readonly class CurrentCatalog implements CurrentCatalogInterface
{
    public function __construct(
        private CommitmentZoneResolverInterface $commitmentZoneResolver,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function currentVersion(CatalogResource $resource, string $reference, ?string $hqId = null): string
    {
        $identityId = $this->catalogRepository->identityIdOfVersion($resource, $reference) ?? $reference;
        $versionId = $this->catalogRepository->publishedVersionIdFor($resource, $identityId, $hqId);
        if ($versionId === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => $resource->value]);
        }

        return $versionId;
    }

    /** @param list<string> $references @return list<\Modules\ServiceCatalog\Application\Dto\OptionRevisionSetDto> */
    public function optionRevisions(array $references, ?string $hqId = null): array
    {
        if ($references === []) {
            return [];
        }
        $options = $this->catalogRepository->optionsWithVersionsByReference($references);
        $byReference = [];
        foreach ($options as $option) {
            $ids = $option->versions->pluck('service_option_version_id')->all();
            $current = null;
            if ($option->status === 'ACTIVE' && ($hqId === null || $option->hq_id === null || $option->hq_id === $hqId)) {
                $current = $option->versions->where('status', VersionLifecycleStatus::Published->value)->sortByDesc('version_number')->first()?->service_option_version_id;
            }
            foreach ([$option->service_option_id, ...$ids] as $reference) {
                $byReference[$reference] = new OptionRevisionSetDto($reference, $option->service_option_id, $ids, $current);
            }
        }
        $result = [];
        foreach ($references as $reference) {
            $result[] = $byReference[$reference] ?? new OptionRevisionSetDto($reference, null, [], null);
        }

        return $result;
    }

    public function relatedVersions(CatalogResource $resource, string $reference): array
    {
        $identityId = $this->catalogRepository->identityIdOfVersion($resource, $reference) ?? $reference;

        return $this->catalogRepository->versionIdsOfIdentity($resource, $identityId);
    }

    /** An unissued quote cannot pin obsolete catalog settings after an edit. */
    public function assertQuoteCurrent(QuoteCatalogReferencesDto $quote): void
    {
        if ($quote->destinationZone !== null) {
            $current = $this->commitmentZoneResolver->group($quote->hqId, $quote->destinationZone->zoneSetId, true);
            if ($current->versionId !== $quote->destinationZone->versionId) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'servicecatalog.commitment_zone_set_changed', details: ['reason_code' => 'CATALOG_CHANGED']);
            }
        }
        $this->assertReferencesCurrent(CatalogResource::Offering, [$quote->offeringVersionId], $quote->hqId);
        if ($quote->typeVersionId !== null && $quote->typeVersionId !== '') {
            $this->assertReferencesCurrent(CatalogResource::ServiceType, [$quote->typeVersionId], $quote->hqId);
        }
        if ($quote->methodVersionId !== null && $quote->methodVersionId !== '') {
            $this->assertReferencesCurrent(CatalogResource::ShippingMethod, [$quote->methodVersionId], $quote->hqId);
        }
        if ($quote->scheduleVersionId !== null && $quote->scheduleVersionId !== '') {
            $this->assertReferencesCurrent(CatalogResource::CommitmentSchedule, [$quote->scheduleVersionId], $quote->hqId);
        }
        $this->assertReferencesCurrent(CatalogResource::Option, $quote->optionVersionIds, $quote->hqId);
    }

    public static function fingerprint(CatalogDraftDto|CommitmentScheduleDto $data): string
    {
        $input = $data instanceof CatalogDraftDto ? CatalogDraftDocument::draft($data) : ScheduleInputDocument::make($data);

        return $data->sourceFingerprint ?? CatalogDraftDocument::inputFingerprint($input);
    }

    /** @param list<string> $references */
    private function assertReferencesCurrent(CatalogResource $resource, array $references, string $hqId): void
    {
        if ($references === []) {
            return;
        }
        $identityKey = $resource->identityKey();
        $versionKey = $resource->versionKey();
        $identitiesByVersion = collect($this->catalogRepository->identityIdsOfVersions($resource, $references));
        $identityIds = array_values(array_unique(array_map(static fn (string $reference): string => $identitiesByVersion->get($reference, $reference), $references)));
        $identities = $this->catalogRepository->lockVisibleIdentities($resource, $identityIds, $hqId);
        $activeIds = $identities->where('status', 'ACTIVE')->pluck($identityKey)->all();
        $versions = $this->catalogRepository->lockPublishedVersionsByIdentity($resource, $activeIds);
        foreach ($references as $reference) {
            $identityId = $identitiesByVersion->get($reference, $reference);
            $current = $versions->get($identityId)?->first();
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'pricing.catalog_dependency_is_inactive_unavailable', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => $resource->value]);
            }
            if ($current->{$versionKey} !== $reference) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'servicecatalog.catalog_settings_changed_calculate_new_quote', details: ['reason_code' => 'CATALOG_CHANGED']);
            }
        }
    }
}
