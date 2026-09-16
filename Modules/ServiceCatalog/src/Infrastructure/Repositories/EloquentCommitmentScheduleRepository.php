<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Modules\Foundation\Application\Data\Page;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepository;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleScopeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingCommitmentBindingRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

final class EloquentCommitmentScheduleRepository implements CommitmentScheduleRepository
{
    public function list(string $hqId, array $filters): Page
    {
        $query = CommitmentScheduleRecord::query()->toBase()->from('commitment_schedules as s')->where('s.hq_id', $hqId)->select(['s.*'])->selectSub(CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->select('v.status')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_status')->selectSub(CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->select('v.version_number')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_number')->selectSub(CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->select('v.commitment_schedule_version_id')->whereColumn('v.commitment_schedule_id', 's.commitment_schedule_id')->orderByDesc('v.version_number')->limit(1), 'latest_version_id');
        if (($filters['search'] ?? '') !== '') {
            $search = '%' . addcslashes((string) $filters['search'], '%_\\') . '%';
            $query->where(fn($q) => $q->where('s.code', 'like', $search)->orWhere('s.title', 'like', $search));
        }
        $page = $query->orderBy('s.code')->paginate(min(100, max(1, (int) ($filters['page_size'] ?? 25))), page: max(1, (int) ($filters['page'] ?? 1)));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function latestVersionId(string $id): ?string
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_id', $id)->orderByDesc('version_number')->value('commitment_schedule_version_id');
    }

    public function published(string $hqId, array $includeVersionIds): array
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->join('commitment_schedules as s', 's.commitment_schedule_id', '=', 'v.commitment_schedule_id')->where('s.hq_id', $hqId)->where(fn($available) => $available->where(fn($active) => $active->where('v.status', 'PUBLISHED')->where('s.status', 'ACTIVE'))->when($includeVersionIds !== [], fn($query) => $query->orWhereIn('v.commitment_schedule_version_id', $includeVersionIds)))->orderBy('s.code')->get(['v.*', 's.code', 's.title'])->all();
    }

    public function identityExists(string $hqId, string $identityId): bool
    {
        return CommitmentScheduleRecord::query()->toBase()->where(['hq_id' => $hqId, 'commitment_schedule_id' => $identityId])->exists();
    }

    public function hasUnpublishedSuccessor(string $identityId): bool
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_id', $identityId)->whereIn('status', ['DRAFT', 'VALIDATING', 'READY_FOR_APPROVAL', 'APPROVED'])->exists();
    }

    public function lockLatestVersion(string $identityId): ?object
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_id', $identityId)->orderByDesc('version_number')->lockForUpdate()->first();
    }

    public function windows(string $versionId): array
    {
        return CommitmentScheduleWindowRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->get()->all();
    }

    public function scopes(string $versionId): array
    {
        return CommitmentScheduleScopeRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->get()->all();
    }

    public function lockVersion(string $hqId, string $versionId): ?object
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where(['commitment_schedule_version_id' => $versionId, 'hq_id' => $hqId])->lockForUpdate()->first();
    }

    public function rename(string $hqId, string $identityId, string $title, \DateTimeInterface $at): void
    {
        CommitmentScheduleRecord::query()->toBase()->where(['commitment_schedule_id' => $identityId, 'hq_id' => $hqId])->update(['title' => $title, 'updated_at' => $at]);
    }

    public function overlaps(string $versionId, array $version): bool
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_id', $version['commitment_schedule_id'])->where('commitment_schedule_version_id', '!=', $versionId)->whereIn('status', ['APPROVED', 'PUBLISHED'])->when($version['valid_from'], fn($q) => $q->where(fn($nested) => $nested->whereNull('valid_to')->orWhere('valid_to', '>', $version['valid_from'])))->when($version['valid_to'], fn($q) => $q->where(fn($nested) => $nested->whereNull('valid_from')->orWhere('valid_from', '<', $version['valid_to'])))->exists();
    }

    public function history(string $identityId): array
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_id', $identityId)->orderByDesc('version_number')->pluck('commitment_schedule_version_id')->all();
    }

    public function pickupVersions(string $hqId, string $nodeId): array
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->join('commitment_schedule_scopes as s', 's.commitment_schedule_version_id', '=', 'v.commitment_schedule_version_id')->join('commitment_schedules as identity', 'identity.commitment_schedule_id', '=', 'v.commitment_schedule_id')->where('identity.status', 'ACTIVE')->where(['v.hq_id' => $hqId, 'v.status' => 'PUBLISHED'])->where(fn($q) => $q->where(fn($scope) => $scope->where('s.scope_type', 'HQ'))->orWhere(fn($scope) => $scope->where('s.scope_type', 'NODE')->where('s.node_id', $nodeId)))->distinct()->pluck('v.commitment_schedule_version_id')->all();
    }

    public function version(string $versionId): ?object
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->first();
    }

    public function pickupWindows(string $versionId): array
    {
        return CommitmentScheduleWindowRecord::query()->toBase()->where(['commitment_schedule_version_id' => $versionId, 'window_type' => 'PICKUP', 'active' => true])->orderBy('start_time')->get()->all();
    }

    public function offeringBinding(string $offeringVersionId): ?object
    {
        return OfferingCommitmentBindingRecord::query()->toBase()->where('service_offering_version_id', $offeringVersionId)->first();
    }

    public function offeringOwner(string $offeringVersionId): ?string
    {
        return ServiceOfferingVersionRecord::query()->toBase()->where('service_offering_version_id', $offeringVersionId)->value('hq_id');
    }

    public function deliveryWindows(string $versionId): array
    {
        return CommitmentScheduleWindowRecord::query()->toBase()->where(['commitment_schedule_version_id' => $versionId, 'window_type' => 'DELIVERY', 'active' => true])->orderBy('day_offset')->orderBy('start_time')->get()->all();
    }

    public function detail(string $hqId, string $versionId): ?object
    {
        return CommitmentScheduleVersionRecord::query()->toBase()->from('commitment_schedule_versions as v')->join('commitment_schedules as s', 's.commitment_schedule_id', '=', 'v.commitment_schedule_id')->where(['v.commitment_schedule_version_id' => $versionId, 's.hq_id' => $hqId])->select(['v.*', 's.code', 's.title', 's.status as identity_status'])->first();
    }

    public function legacyBindings(string $identityId): array
    {
        return OfferingCommitmentBindingRecord::query()->toBase()->from('service_offering_commitment_bindings as b')->join('commitment_schedule_versions as v', 'v.commitment_schedule_version_id', '=', 'b.commitment_schedule_version_id')->where('v.commitment_schedule_id', $identityId)->get()->all();
    }

    public function orderedWindows(string $versionId): array
    {
        return CommitmentScheduleWindowRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->orderBy('window_type')->orderBy('start_time')->get()->all();
    }

    public function deleteWindows(string $versionId): void
    {
        CommitmentScheduleWindowRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->delete();
    }

    public function deleteScopes(string $versionId): void
    {
        CommitmentScheduleScopeRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->delete();
    }

    public function activeWindow(string $versionId, string $type, string $code): ?object
    {
        return CommitmentScheduleWindowRecord::query()->toBase()->where([
            'commitment_schedule_version_id' => $versionId,
            'window_type' => $type,
            'window_code' => $code,
            'active' => true,
        ])->first();
    }

    public function insertIdentity(array $attributes): void
    {
        CommitmentScheduleRecord::query()->toBase()->insert($attributes);
    }

    public function insertVersion(array $attributes): void
    {
        CommitmentScheduleVersionRecord::query()->toBase()->insert($attributes);
    }

    public function insertWindow(array $attributes): void
    {
        CommitmentScheduleWindowRecord::query()->toBase()->insert($attributes);
    }

    public function insertScope(array $attributes): void
    {
        CommitmentScheduleScopeRecord::query()->toBase()->insert($attributes);
    }

    public function updateVersion(string $versionId, array $changes): void
    {
        CommitmentScheduleVersionRecord::query()->toBase()->where('commitment_schedule_version_id', $versionId)->update($changes);
    }
}
