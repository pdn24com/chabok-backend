<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Modules\Manifest\Application\Repositories\ManifestWorkflowRepository;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final class EloquentManifestWorkflowRepository implements ManifestWorkflowRepository
{
    public function successfulSourceParcel(?string $hqId, ?string $sourceManifestId, ?string $parcelId): ?object
    {
        return ManifestParcelRecord::query()->toBase()->where([
            'hq_id' => $hqId,
            'manifest_id' => $sourceManifestId,
            'parcel_id' => $parcelId,
            'manifest_parcel_status' => 'SUCCEEDED',
        ])->first();
    }

    public function closedOutboundManifests(string $hq, string $node): array
    {
        return ManifestRecord::query()->toBase()->from('manifests as m')->where(['m.hq_id' => $hq, 'm.node_id' => $node, 'm.manifest_status' => 'OF', 'm.state' => 'CLOSED'])->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('manifest_parcels as mp')->join('parcels as parcel', 'parcel.parcel_id', '=', 'mp.parcel_id')->whereColumn('mp.manifest_id', 'm.manifest_id')->where(['mp.manifest_parcel_status' => 'SUCCEEDED', 'parcel.current_status' => 'OF']))->orderBy('m.closed_at')->get()->all();
    }

    public function receptionManifests(string $hq, string $node): array
    {
        return ManifestRecord::query()->toBase()->where(['hq_id' => $hq, 'manifest_status' => 'OS', 'state' => 'CLOSED', 'destination_node_id' => $node])->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('manifest_parcels as mp')->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')->whereColumn('mp.manifest_id', 'manifests.manifest_id')->where(['mp.manifest_parcel_status' => 'SUCCEEDED', 'p.current_status' => 'OS']))->orderBy('closed_at')->get()->all();
    }

    public function hasTransitReception(?string $manifestId, string $node): bool
    {
        return ManifestParcelRecord::query()->toBase()->from('manifest_parcels as mp')->join('route_plan_legs as current', 'current.route_plan_leg_id', '=', 'mp.route_plan_leg_id')->where(['mp.manifest_id' => $manifestId, 'mp.manifest_parcel_status' => 'SUCCEEDED'])->whereExists(fn(Builder $q) => $q->selectRaw('1')->from('route_plan_legs as next')->whereColumn('next.route_plan_id', 'current.route_plan_id')->whereRaw('next.leg_order = current.leg_order + 1')->where('next.origin_node_id', $node))->exists();
    }

    public function hasFinalReception(?string $manifestId): bool
    {
        return ManifestParcelRecord::query()->toBase()->from('manifest_parcels as mp')->join('route_plan_legs as current', 'current.route_plan_leg_id', '=', 'mp.route_plan_leg_id')->where(['mp.manifest_id' => $manifestId, 'mp.manifest_parcel_status' => 'SUCCEEDED'])->whereNotExists(fn(Builder $q) => $q->selectRaw('1')->from('route_plan_legs as next')->whereColumn('next.route_plan_id', 'current.route_plan_id')->whereRaw('next.leg_order = current.leg_order + 1'))->exists();
    }

    public function updateManifest(?string $id, array $changes): void
    {
        ManifestRecord::query()->toBase()->where('manifest_id', $id)->update($changes);
    }

    public function manifest(?string $hqId, string $node, ?string $id): ?object
    {
        return ManifestRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $node, 'manifest_id' => $id])->first();
    }

    public function firstManifestParcel(?string $manifestId): ?object
    {
        return ManifestParcelRecord::query()->toBase()->from('manifest_parcels as mp')->join('parcels as p', 'p.parcel_id', '=', 'mp.parcel_id')->where(['mp.manifest_id' => $manifestId])->orderBy('mp.created_at')->first(['p.consignment_id', 'p.parcel_id']);
    }

    public function lockProcessableRows(?string $hqId, ?string $manifestId): array
    {
        return ManifestParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])->whereIn('manifest_parcel_status', ['PENDING', 'VALIDATED', 'FAILED'])->orderBy('parcel_id')->lockForUpdate()->get()->all();
    }

    public function updateManifestParcel(?string $manifestParcelId, array $changes): void
    {
        ManifestParcelRecord::query()->toBase()->where('manifest_parcel_id', $manifestParcelId)->update($changes);
    }

    public function lockRows(?string $hqId, ?string $manifestId): array
    {
        return ManifestParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])->orderBy('parcel_id')->lockForUpdate()->get()->all();
    }

    public function lockManifest(?string $hqId, string $node, ?string $id): ?object
    {
        return ManifestRecord::query()->toBase()->where(['hq_id' => $hqId, 'node_id' => $node, 'manifest_id' => $id])->lockForUpdate()->first();
    }

    public function userDisplayName(?string $submittedBy): ?string
    {
        return DB::table('users')->where('user_id', $submittedBy)->value('display_name');
    }
}
