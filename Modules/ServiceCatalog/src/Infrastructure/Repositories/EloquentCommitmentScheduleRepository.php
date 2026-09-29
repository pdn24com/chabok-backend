<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\Enums\VersionLifecycleStatus;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleScopeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;

final class EloquentCommitmentScheduleRepository implements CommitmentScheduleRepositoryInterface
{
    /** Rows written per statement when a clone copies a version's children. */
    private const BATCH_SIZE = 100;

    /** Statuses that mean a successor draft is already in flight. */
    /** Versions that already claim an effective interval for their schedule. */
    /** The graph a schedule response renders. */
    private const VERSION_RELATIONS = ['schedule.legacyBindings', 'windows', 'scopes'];

    public function identityExists(?string $hqId, string $identityId): bool
    {
        return CommitmentScheduleRecord::query()->where(['hq_id' => $hqId, 'commitment_schedule_id' => $identityId])->exists();
    }

    public function lockIdentity(?string $hqId, string $identityId): ?CommitmentScheduleRecord
    {
        return CommitmentScheduleRecord::query()->where(['hq_id' => $hqId, 'commitment_schedule_id' => $identityId])->lockForUpdate()->first();
    }

    public function updateIdentity(?string $hqId, string $identityId, array $changes): void
    {
        CommitmentScheduleRecord::query()->where(['hq_id' => $hqId, 'commitment_schedule_id' => $identityId])->update($changes);
    }

    public function paginateIdentities(?string $hqId, string $search, int $page, int $pageSize): LengthAwarePaginator
    {
        $query = CommitmentScheduleRecord::query()->where('hq_id', $hqId)->with('latestVersion');
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn ($q) => $q->where('code', 'like', $term)->orWhere('title', 'like', $term));
        }

        return $query->orderBy('code')->paginate($pageSize, page: $page);
    }

    public function identitiesWithLatestVersion(?string $hqId, array $identityIds): Collection
    {
        return CommitmentScheduleRecord::query()->where('hq_id', $hqId)->whereIn('commitment_schedule_id', $identityIds)
            ->with('latestVersion')->get()->keyBy('commitment_schedule_id');
    }

    public function lockTenantVersion(?string $hqId, string $versionId): ?CommitmentScheduleVersionRecord
    {
        return CommitmentScheduleVersionRecord::query()->where(['hq_id' => $hqId, 'commitment_schedule_version_id' => $versionId])->lockForUpdate()->first();
    }

    public function applyVersion(CommitmentScheduleVersionRecord $version, array $changes): void
    {
        $version->forceFill($changes)->save();
    }

    public function publishedVersionExists(?string $hqId, string $versionId): bool
    {
        return CommitmentScheduleVersionRecord::query()
            ->where(['hq_id' => $hqId, 'commitment_schedule_version_id' => $versionId, 'status' => VersionLifecycleStatus::Published->value])->exists();
    }

    public function publishedVersionsScopedToNode(?string $hqId, string $nodeId): Collection
    {
        return CommitmentScheduleVersionRecord::query()->where(['hq_id' => $hqId, 'status' => VersionLifecycleStatus::Published->value])
            ->whereHas('schedule', fn ($schedule) => $schedule->where('status', 'ACTIVE'))
            ->whereHas('scopes', fn ($scopes) => $scopes->where('scope_type', 'HQ')->orWhere(fn ($scope) => $scope->where('scope_type', 'NODE')->where('node_id', $nodeId)))
            ->with('windows')->get();
    }

    public function hasOverlappingEffectiveVersion(string $identityId, string $versionId, mixed $validFrom, mixed $validTo): bool
    {
        return CommitmentScheduleVersionRecord::query()->where('commitment_schedule_id', $identityId)
            ->where('commitment_schedule_version_id', '!=', $versionId)->whereIn('status', VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::effective()))
            ->when($validFrom, fn ($q) => $q->where(fn ($interval) => $interval->whereNull('valid_to')->orWhere('valid_to', '>', $validFrom)))
            ->when($validTo, fn ($q) => $q->where(fn ($interval) => $interval->whereNull('valid_from')->orWhere('valid_from', '<', $validTo)))
            ->exists();
    }

    public function tenantVersions(string $hqId): Collection
    {
        return $this->tenantVersionQuery($hqId)->get();
    }

    public function findTenantVersion(string $hqId, string $versionId): ?CommitmentScheduleVersionRecord
    {
        return $this->tenantVersionQuery($hqId)->where('commitment_schedule_version_id', $versionId)->first();
    }

    public function versionHistoryOf(string $hqId, string $identityId): Collection
    {
        return $this->tenantVersionQuery($hqId)->where('commitment_schedule_id', $identityId)->orderByDesc('version_number')->get();
    }

    public function availableVersions(string $hqId, array $includedVersionIds): Collection
    {
        return $this->tenantVersionQuery($hqId)
            ->where(fn ($query) => $query->where(fn ($published) => $published->where('status', VersionLifecycleStatus::Published->value)->whereHas('schedule', fn ($schedule) => $schedule->where('status', 'ACTIVE')))
                ->orWhereIn('commitment_schedule_version_id', $includedVersionIds))
            ->get()->sortBy('schedule.code')->values();
    }

    public function hasUnpublishedSuccessor(CommitmentScheduleRecord $identity): bool
    {
        return $identity->versions()->whereIn('status', VersionLifecycleStatus::valuesOf(VersionLifecycleStatus::unpublished()))->exists();
    }

    public function lockLatestVersionWithChildren(CommitmentScheduleRecord $identity): ?CommitmentScheduleVersionRecord
    {
        return $identity->versions()->orderByDesc('version_number')->lockForUpdate()->with(['windows', 'scopes'])->first();
    }

    public function replicateAsDraft(CommitmentScheduleVersionRecord $previous, array $overrides): string
    {
        $copy = $previous->replicate(['approved_by', 'published_by', 'approved_at', 'published_at', 'content_digest'])->forceFill($overrides);
        $copy->save();
        $newVersionId = (string) $copy->getKey();
        $this->copyChildren($previous, $newVersionId);

        return $newVersionId;
    }

    private function copyChildren(CommitmentScheduleVersionRecord $previous, string $newVersionId): void
    {
        $windows = [];
        foreach ($previous->windows as $window) {
            $windows[] = $window->replicate()->forceFill([

                'commitment_schedule_version_id' => $newVersionId,
            ])->getAttributes();
        }
        foreach (array_chunk($windows, self::BATCH_SIZE) as $chunk) {
            CommitmentScheduleWindowRecord::query()->insert($chunk);
        }
        $scopes = [];
        foreach ($previous->scopes as $scope) {
            $scopes[] = $scope->replicate()->forceFill([

                'commitment_schedule_version_id' => $newVersionId,
            ])->getAttributes();
        }
        foreach (array_chunk($scopes, self::BATCH_SIZE) as $chunk) {
            CommitmentScheduleScopeRecord::query()->insert($chunk);
        }
    }

    private function tenantVersionQuery(string $hqId): Builder
    {
        return CommitmentScheduleVersionRecord::query()->whereHas('schedule', fn ($schedule) => $schedule->where('hq_id', $hqId))
            ->with(self::VERSION_RELATIONS);
    }
}
