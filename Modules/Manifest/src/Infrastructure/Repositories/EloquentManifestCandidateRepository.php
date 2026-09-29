<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Application\Dto\ManifestCandidateScopeDto;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Application\Repositories\ManifestCandidateRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final class EloquentManifestCandidateRepository implements ManifestCandidateRepositoryInterface
{
    /** Task states that still hold a Consignment at the Node it was created for. */
    private const OPEN_TASK_STATUSES = ['PENDING', 'ASSIGNED', 'IN_PROGRESS'];

    public function paginateCandidates(string $hqId, ManifestFiltersDto $filters, ManifestCandidateScopeDto $scope): LengthAwarePaginator
    {
        return $this->candidateQuery($hqId, $filters, $scope)->orderBy('parcel_number')->paginate($filters->pageSize, page: $filters->page);
    }

    public function candidates(string $hqId, ManifestFiltersDto $filters, ManifestCandidateScopeDto $scope): Collection
    {
        return $this->candidateQuery($hqId, $filters, $scope)->get();
    }

    public function visibleParcelIds(string $hqId, string $nodeId): array
    {
        return $this->visible($hqId, $nodeId)->pluck('parcel_id')->all();
    }

    public function lockVisibleByIdentifiers(string $hqId, string $nodeId, array $identifiers): Collection
    {
        return $this->visible($hqId, $nodeId)
            ->where(fn ($q) => $q->whereIn('parcel_number', $identifiers)->orWhereHas('consignment', fn ($c) => $c->whereIn('consignment_number', $identifiers)))
            ->with('consignment')->orderBy('parcel_id')->lockForUpdate()->get()->sortBy('parcel_number');
    }

    private function visible(string $hqId, string $nodeId): Builder
    {
        return ParcelRecord::query()->where('hq_id', $hqId)
            ->whereHas('consignment', fn ($q) => $q->where('hq_id', $hqId))
            ->where(fn ($q) => $q->where('current_node_id', $nodeId)
                ->orWhereHas('activeRouteLeg', fn ($leg) => $leg->where('destination_node_id', $nodeId)->where('status', 'IN_TRANSIT'))
                ->orWhereHas('consignment', fn ($consignment) => $consignment
                    ->where('pickup_node_id', $nodeId)->orWhere('delivery_node_id', $nodeId)
                    ->orWhereHas('pickupTasks', fn ($task) => $task->where('node_id', $nodeId)->whereIn('status', self::OPEN_TASK_STATUSES))
                    ->orWhereHas('deliveryTasks', fn ($task) => $task->where('node_id', $nodeId)->whereIn('status', self::OPEN_TASK_STATUSES))));
    }

    private function candidateQuery(string $hqId, ManifestFiltersDto $filters, ManifestCandidateScopeDto $scope): Builder
    {
        $query = ParcelRecord::query()->where('hq_id', $hqId)
            ->whereHas('consignment', fn ($q) => $q->where('hq_id', $hqId)->where(fn ($q) => $q->whereNull('service_offering_id')->orWhere('commercial_pricing_state', 'LOCKED')))
            ->whereNotIn('id', ManifestParcelRecord::query()->select('parcel_id')->where('hq_id', $hqId)->whereNotNull('active_slot'))
            ->with('consignment')
            ->whereIn('current_status', $scope->sourceStatuses);
        if ($filters->search !== null) {
            $search = '%'.$filters->search.'%';
            $query->where(fn ($q) => $q->where('parcel_number', 'like', $search)->orWhereHas('consignment', fn ($c) => $c->where('consignment_number', 'like', $search)));
        }
        if ($scope->requiresSourceManifest) {
            $query->whereIn('id', ManifestParcelRecord::query()->select('parcel_id')
                ->where(['hq_id' => $hqId, 'manifest_id' => $scope->sourceManifestId, 'manifest_parcel_status' => ManifestParcelStatus::Succeeded->value]));
        }
        if ($scope->custodyType !== null) {
            $query->where('current_custody_type', $scope->custodyType);
        }
        if ($scope->nodeId !== null) {
            $query->where(fn ($q) => $q->where('current_node_id', $scope->nodeId)->orWhereHas('consignment', fn ($c) => $c->where('pickup_node_id', $scope->nodeId)->orWhere('delivery_node_id', $scope->nodeId)));
        }

        return $query;
    }
}
