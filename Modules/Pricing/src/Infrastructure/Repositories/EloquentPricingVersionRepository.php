<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingIdentityRecordInterface;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingVersionRecordInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final class EloquentPricingVersionRepository implements PricingVersionRepositoryInterface
{
    /** Columns a clone deliberately leaves behind, because approval and publication do not carry over. */
    private const UNCOPIED_COLUMNS = ['approved_by', 'published_by', 'approved_at', 'published_at', 'content_digest'];

    public function identityVisible(PricingResource $resource, string $identityId, ?string $hqId): bool
    {
        return $this->identities($resource)->where($resource->identityKey(), $identityId)
            ->where(fn ($scope) => $scope->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }

    public function lockTenantIdentity(PricingResource $resource, string $identityId, ?string $hqId): ?PricingIdentityRecordInterface
    {
        return $this->identities($resource)->where([$resource->identityKey() => $identityId, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function versionIdsNewestFirst(PricingResource $resource, string $identityId): array
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)
            ->orderByDesc('version_number')->pluck($resource->versionKey())->all();
    }

    public function lockTenantVersion(PricingResource $resource, string $versionId, ?string $hqId): ?PricingVersionRecordInterface
    {
        return $this->versions($resource)->where('hq_id', $hqId)->where($resource->versionKey(), $versionId)->lockForUpdate()->first();
    }

    public function lockLatestVersionOf(PricingResource $resource, string $identityId): ?PricingVersionRecordInterface
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)
            ->orderByDesc('version_number')->lockForUpdate()->first();
    }

    public function hasVersionWithStatus(PricingResource $resource, string $identityId, array $statuses): bool
    {
        return $this->versions($resource)->where($resource->identityKey(), $identityId)->whereIn('status', $statuses)->exists();
    }

    public function hasOverlappingEffectiveVersion(PricingResource $resource, PricingVersionPeriodDto $period): bool
    {
        // Query bindings format DateTime values to seconds by default; these
        // version boundaries are persisted at microsecond precision.
        $validFrom = CarbonImmutable::instance($period->validFrom)->utc()->format('Y-m-d H:i:s.u');
        $query = $this->versions($resource)
            ->where($resource->identityKey(), $period->familyId)->where($resource->versionKey(), '!=', $period->versionId)
            ->whereIn('status', VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::effective()))->where('version_number', '>=', $period->versionNumber)
            ->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $validFrom));
        if ($period->validTo !== null) {
            $query->where('valid_from', '<', CarbonImmutable::instance($period->validTo)->utc()->format('Y-m-d H:i:s.u'));
        }

        return $query->exists();
    }

    public function replicateAsDraft(PricingResource $resource, PricingVersionRecordInterface $previous, array $overrides): string
    {
        $copy = $previous->replicate([$resource->versionKey(), ...self::UNCOPIED_COLUMNS])->forceFill($overrides);
        $copy->save();

        return (string) $copy->getKey();
    }

    private function identities(PricingResource $resource): Builder
    {
        return $resource === PricingResource::Tariffs ? TariffFamilyRecord::query() : PricingZoneSetRecord::query();
    }

    private function versions(PricingResource $resource): Builder
    {
        return $resource === PricingResource::Tariffs ? TariffVersionRecord::query() : PricingZoneSetVersionRecord::query();
    }
}
