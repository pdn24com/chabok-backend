<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Contracts\ManifestConsignmentAccessInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final class EloquentManifestConsignmentAccess implements ManifestConsignmentAccessInterface
{
    public function consignment(?string $hqId, ?string $consignmentId): ?ConsignmentRecord
    {
        return ConsignmentRecord::query()

            ->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])
            ->first();
    }

    public function custodyEvents(string $hq, string $manifest): Collection
    {
        return CustodyEventRecord::query()

            ->where(['hq_id' => $hq, 'manifest_id' => $manifest])
            ->orderBy('event_sequence')
            ->get();
    }

    public function saveTenantParcels(string $hqId, Collection $parcels): void
    {
        $records = [];
        foreach ($parcels as $parcel) {
            if (! $parcel->exists || $parcel->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            $records[] = $parcel->getAttributes();
        }
        foreach (array_chunk($records, 100) as $batch) {
            ParcelRecord::query()->upsert($batch, ['id'], ['current_status', 'current_node_id', 'current_custody_type', 'current_custodian_id', 'active_route_plan_id', 'active_route_plan_leg_id', 'version', 'updated_at']);
        }
    }

    public function deliveryNodes(string $hqId, array $consignmentIds): array
    {
        return ConsignmentRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', array_values(array_unique($consignmentIds)))
            ->pluck('delivery_node_id', 'consignment_id')->all();
    }

    public function lockAtPickupNode(
        ?string $hqId,
        string $consignmentId,
        string $node,
    ): ?ConsignmentRecord {
        return ConsignmentRecord::query()

            ->where([
                'hq_id' => $hqId,
                'consignment_id' => $consignmentId,
                'pickup_node_id' => $node,
            ])
            ->lockForUpdate()
            ->first();
    }

    public function updatePickupDriver(?string $consignmentId, array $changes): void
    {
        ConsignmentRecord::query()

            ->where('consignment_id', $consignmentId)
            ->update($changes);
    }
}
