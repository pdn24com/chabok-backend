<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Enums\ManifestTransition;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final class EloquentManifestRepository implements ManifestRepositoryInterface
{
    public function findAtNode(?string $hqId, string $nodeId, string $manifestId): ?ManifestRecord
    {
        return ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'manifest_id' => $manifestId])->first();
    }

    public function lockAtNode(?string $hqId, string $nodeId, string $manifestId): ?ManifestRecord
    {
        return ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'manifest_id' => $manifestId])->lockForUpdate()->first();
    }

    public function paginateAtNode(?string $hqId, string $nodeId, ManifestFiltersDto $filters): LengthAwarePaginator
    {
        $query = ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId]);
        if ($filters->search !== null) {
            $query->where('manifest_number', 'like', '%'.addcslashes($filters->search, '%_\\').'%');
        }
        if ($filters->states !== []) {
            $query->whereIn('state', $filters->states);
        }
        if ($filters->statuses !== []) {
            $query->whereIn('manifest_status', $filters->statuses);
        }

        return $query->orderByDesc('created_at')->orderByDesc('manifest_id')->paginate($filters->pageSize, page: $filters->page);
    }

    public function update(string $manifestId, array $changes): void
    {
        ManifestRecord::query()->where('manifest_id', $manifestId)->update($changes);
    }

    public function closedOutboundTransfers(string $hqId, string $nodeId): Collection
    {
        $status = ManifestTransition::OutboundConfirmation->value;

        return ManifestRecord::query()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'manifest_status' => $status, 'state' => ManifestState::Closed->value])
            ->whereHas('parcels', fn ($rows) => $rows->where('manifest_parcel_status', ManifestParcelStatus::Succeeded->value)
                ->whereHas('parcel', fn ($parcel) => $parcel->where('current_status', $status)))
            ->orderBy('closed_at')->get();
    }

    public function inboundLinehaulArrivals(string $hqId, string $nodeId): Collection
    {
        $status = ManifestTransition::LinehaulDeparture->value;

        return ManifestRecord::query()->where(['hq_id' => $hqId, 'manifest_status' => $status, 'state' => ManifestState::Closed->value, 'destination_node_id' => $nodeId])
            ->whereHas('parcels', fn ($rows) => $rows->where('manifest_parcel_status', ManifestParcelStatus::Succeeded->value)
                ->whereHas('parcel', fn ($parcel) => $parcel->where('current_status', $status)))
            ->with(['parcels' => fn ($rows) => $rows->where('manifest_parcel_status', ManifestParcelStatus::Succeeded->value)->with('routeLeg.plan.legs')])
            ->orderBy('closed_at')->get();
    }

    public function outcomesForConsignment(string $hqId, string $consignmentId): Collection
    {
        $outcomes = fn ($parcels) => $parcels->where('hq_id', $hqId)
            ->whereHas('parcel', fn ($parcel) => $parcel->where('consignment_id', $consignmentId))->orderBy('created_at');

        return ManifestRecord::query()->where('hq_id', $hqId)->whereHas('parcels', $outcomes)
            ->with(['parcels' => $outcomes])->orderBy('created_at')->get();
    }
}
