<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Modules\Consignment\Application\Contracts\ManifestConsignmentAccess;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;

final class EloquentManifestConsignmentAccess implements ManifestConsignmentAccess
{
    public function consignment(?string $hqId, ?string $consignmentId): ?object
    {
        return ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->first();
    }

    public function custodyEvents(string $hq, string $manifest): array
    {
        return CustodyEventRecord::query()->toBase()->where(['hq_id' => $hq, 'manifest_id' => $manifest])->orderBy('event_sequence')->get()->all();
    }

    public function lockParcel(?string $hqId, ?string $parcelId): ?object
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'parcel_id' => $parcelId])->lockForUpdate()->first();
    }

    public function updateTenantParcel(?string $hqId, ?string $parcelId, array $changes): void
    {
        ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'parcel_id' => $parcelId])->update($changes);
    }

    public function hasDeliveryNode(?string $hqId, ?string $consignmentId, string $node): bool
    {
        return ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'delivery_node_id' => $node])->exists();
    }

    public function lockAtPickupNode(?string $hqId, string $consignmentId, string $node): ?object
    {
        return ConsignmentRecord::query()->toBase()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId, 'pickup_node_id' => $node])->lockForUpdate()->first();
    }

    public function appendCustodyEvent(array $attributes): void
    {
        CustodyEventRecord::query()->toBase()->insert($attributes);
    }

    public function updatePickupDriver(?string $consignmentId, array $changes): void
    {
        ConsignmentRecord::query()->toBase()->where('consignment_id', $consignmentId)->update($changes);
    }

    public function appendStatusEvent(array $attributes): void
    {
        StatusEventRecord::query()->toBase()->insert($attributes);
    }

    public function parcel(string $hqId, string $parcelId): ?object
    {
        return ParcelRecord::query()->toBase()->where(['hq_id' => $hqId, 'parcel_id' => $parcelId])->first();
    }
}
