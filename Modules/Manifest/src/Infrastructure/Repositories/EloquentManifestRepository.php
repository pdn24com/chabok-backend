<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Modules\Manifest\Domain\ManifestWriteConflict;
use Modules\Foundation\Application\Data\Page;
use Modules\Manifest\Application\Repositories\ManifestRepository;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final class EloquentManifestRepository implements ManifestRepository
{
    private function visibleParcelAtNode($query, string $nodeId): void
    {
        $query->where('p.current_node_id', $nodeId)->orWhereExists(fn($q) => $q->selectRaw('1')->from('route_plan_legs as rpl')->whereColumn('rpl.route_plan_leg_id', 'p.active_route_plan_leg_id')->where('rpl.destination_node_id', $nodeId)->where('rpl.status', 'IN_TRANSIT'))->orWhereExists(fn($q) => $q->selectRaw('1')->from('pickup_tasks as pt')->whereColumn('pt.consignment_id', 'p.consignment_id')->where('pt.node_id', $nodeId)->whereIn('pt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))->orWhereExists(fn($q) => $q->selectRaw('1')->from('delivery_tasks as dt')->whereColumn('dt.consignment_id', 'p.consignment_id')->where('dt.node_id', $nodeId)->whereIn('dt.status', ['PENDING', 'ASSIGNED', 'IN_PROGRESS']))->orWhere('c.pickup_node_id', $nodeId)->orWhere('c.delivery_node_id', $nodeId);
    }

    public function resolveInput(string $hqId, string $nodeId, string $identifier): array
    {
        return DB::table('parcels as p')->join('consignments as c', function ($j): void {
            $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $hqId)->where(fn($q) => $this->visibleParcelAtNode($q, $nodeId))->where(fn($q) => $q->where('p.parcel_number', $identifier)->orWhere('c.consignment_number', $identifier))->select(['p.*'])->orderBy('p.parcel_number')->get()->all();
    }

    public function list(string $hqId, string $nodeId, array $filters): Page
    {
        $query = ManifestRecord::query()->toBase()->from('manifests as m')->where(['m.hq_id' => $hqId, 'm.node_id' => $nodeId])->select('m.*');
        if (($filters['search'] ?? null) !== null) {
            $query->where('m.manifest_number', 'like', '%' . addcslashes((string) $filters['search'], '%_\\') . '%');
        }
        foreach (['state', 'manifest_status'] as $field) {
            if (!empty($filters[$field])) {
                $query->whereIn("m.{$field}", (array) $filters[$field]);
            }
        }
        $page = $query->orderByDesc('m.created_at')->orderByDesc('m.manifest_id')->paginate((int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
        return new Page($page->items(), $page->currentPage(), $page->perPage(), $page->total());
    }

    public function candidates(string $hqId, array $filters, array $scope): Page
    {
        $query = DB::table('parcels as p')->join('consignments as c', function ($join): void {
            $join->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('p.hq_id', $hqId)->where(fn($q) => $q->whereNull('c.service_offering_id')->orWhere('c.commercial_pricing_state', 'LOCKED'))->whereNotExists(fn($q) => $q->selectRaw('1')->from('manifest_parcels as mp')->whereColumn('mp.parcel_id', 'p.parcel_id')->whereNotNull('mp.active_slot'))->when(($filters['search'] ?? null) !== null, fn($q) => $q->where(fn($s) => $s->where('p.parcel_number', 'like', '%' . (string) $filters['search'] . '%')->orWhere('c.consignment_number', 'like', '%' . (string) $filters['search'] . '%')))->select(['p.*', 'c.consignment_number', 'c.receiver_contact_name']);
        $this->applyCandidateScope($query, $scope);
        $paginator = $query->orderBy('p.parcel_number')->paginate((int) ($filters['page_size'] ?? 25), page: (int) ($filters['page'] ?? 1));
        return new Page($paginator->items(), $paginator->currentPage(), $paginator->perPage(), $paginator->total());
    }

    private function applyCandidateScope(\Illuminate\Database\Query\Builder $query, array $scope): void
    {
        $query->whereIn('p.current_status', $scope['source_statuses']);
        if ($scope['requires_source_manifest']) {
            $query->whereExists(fn($q) => $q->selectRaw('1')->from('manifest_parcels as source_mp')->whereColumn('source_mp.parcel_id', 'p.parcel_id')->where(['source_mp.manifest_id' => $scope['source_manifest_id'], 'source_mp.manifest_parcel_status' => 'SUCCEEDED']));
        }
        if ($scope['custody_type'] !== null) {
            $query->where('p.current_custody_type', $scope['custody_type']);
        }
        if ($scope['node_id'] !== null) {
            $query->where(fn($q) => $q->where('p.current_node_id', $scope['node_id'])->orWhere('c.pickup_node_id', $scope['node_id'])->orWhere('c.delivery_node_id', $scope['node_id']));
        }
    }

    public function batchCounts(string $hqId, array $manifestIds): array
    {
        return ManifestParcelRecord::query()->toBase()->where('hq_id', $hqId)->whereIn('manifest_id', $manifestIds)->selectRaw('manifest_id, manifest_parcel_status, COUNT(*) AS total')->groupBy('manifest_id', 'manifest_parcel_status')->get()->groupBy('manifest_id')->all();
    }

    public function hasParcels(string $id): bool
    {
        return ManifestParcelRecord::query()->toBase()->where('manifest_id', $id)->exists();
    }

    public function containsParcel(string $parcelId, string $id): bool
    {
        return ManifestParcelRecord::query()->toBase()->where(['manifest_id' => $id, 'parcel_id' => $parcelId])->exists();
    }

    public function lockValidationRows(string $id): array
    {
        return ManifestParcelRecord::query()->toBase()->where('manifest_id', $id)->whereIn('manifest_parcel_status', ['PENDING', 'VALIDATED'])->lockForUpdate()->get()->all();
    }

    public function parcelDetails(string $id): array
    {
        return ManifestParcelRecord::query()->toBase()->from('manifest_parcels as mp')->join('parcels as p', function ($j): void {
            $j->on('p.parcel_id', '=', 'mp.parcel_id')->on('p.hq_id', '=', 'mp.hq_id');
        })->join('consignments as c', function ($j): void {
            $j->on('c.consignment_id', '=', 'p.consignment_id')->on('c.hq_id', '=', 'p.hq_id');
        })->where('mp.manifest_id', $id)->orderBy('mp.created_at')->get([
            'mp.*',
            'p.parcel_number',
            'p.current_status',
            'p.current_node_id',
            'p.current_custody_type',
            'p.current_custodian_id',
            'p.active_route_plan_id',
            'p.active_route_plan_leg_id',
            'p.version as parcel_version',
            'c.consignment_id',
            'c.consignment_number',
            'c.receiver_contact_name',
        ])->all();
    }

    public function statusEvents(string $hqId, string $manifestId): array
    {
        return DB::table('consignment_status_events as se')->join('consignments as c', function ($join): void {
            $join->on('c.consignment_id', '=', 'se.consignment_id')->on('c.hq_id', '=', 'se.hq_id');
        })->leftJoin('parcels as p', function ($join): void {
            $join->on('p.parcel_id', '=', 'se.parcel_id')->on('p.hq_id', '=', 'se.hq_id');
        })->leftJoin('nodes as n', function ($join): void {
            $join->on('n.node_id', '=', 'se.node_id')->on('n.hq_id', '=', 'se.hq_id');
        })->leftJoin('users as u', 'u.user_id', '=', 'se.initiator_id')->where(['se.hq_id' => $hqId, 'se.manifest_id' => $manifestId])->orderBy('se.event_sequence')->orderBy('se.created_at')->get([
            'se.status_event_id',
            'se.event_sequence',
            'se.consignment_id',
            'c.consignment_number',
            'se.parcel_id',
            'p.parcel_number',
            'se.previous_status',
            'se.new_status',
            'se.reason_code',
            'se.created_at',
            'se.initiator_id',
            'u.display_name as initiator_name',
            'se.node_id',
            'n.node_title',
        ])->all();
    }

    public function auditEvents(string $hqId, string $manifestId): array
    {
        return DB::table('audit_events as ae')->leftJoin('users as u', 'u.user_id', '=', 'ae.initiator_id')->where(['ae.hq_id' => $hqId, 'ae.target_type' => 'MANIFEST', 'ae.target_id' => $manifestId])->orderBy('ae.created_at')->orderBy('ae.audit_id')->get([
            'ae.audit_id',
            'ae.action_key',
            'ae.initiator_id',
            'u.display_name as initiator_name',
            'ae.correlation_id',
            'ae.created_at',
        ])->all();
    }

    public function find(string $hqId, string $nodeId, string $id): ?object
    {
        return ManifestRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'manifest_id' => $id])->first();
    }

    public function lock(string $hqId, string $nodeId, string $id): ?object
    {
        return ManifestRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $nodeId, 'manifest_id' => $id])->lockForUpdate()->first();
    }

    public function counts(string $id): array
    {
        return ManifestParcelRecord::query()->toBase()->where('manifest_id', $id)->selectRaw('manifest_parcel_status, COUNT(*) total')->groupBy('manifest_parcel_status')->pluck('total', 'manifest_parcel_status')->all();
    }

    public function insert(array $attributes): void
    {
        ManifestRecord::query()->toBase()->insert($attributes);
    }

    public function insertParcel(array $attributes): void
    {
        try {
            ManifestParcelRecord::query()->toBase()->insert($attributes);
        } catch (QueryException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
            throw new ManifestWriteConflict(previous: $error);
        }
    }

    public function update(string $id, array $changes): void
    {
        ManifestRecord::query()->toBase()->where('manifest_id', $id)->update($changes);
    }

    public function updateParcel(string $id, array $changes): void
    {
        ManifestParcelRecord::query()->toBase()->where('manifest_parcel_id', $id)->update($changes);
    }
}
