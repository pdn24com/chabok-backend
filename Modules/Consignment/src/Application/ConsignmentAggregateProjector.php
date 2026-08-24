<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class ConsignmentAggregateProjector
{
    /** @return array{status:string,mode:string,parcel_counts:array<string,int>,parcel_total:int,target_count:int} */
    public function project(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $targetStatus,
        ?string $nodeId,
        ?string $manifestId,
        string $reasonCode,
        ?string $driverId = null,
    ): array {
        $consignment = DB::table('consignments')->where([
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
        ])->lockForUpdate()->first();
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        $counts = DB::table('parcels')->where([
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
        ])->selectRaw('current_status, COUNT(*) AS aggregate_count')
            ->groupBy('current_status')
            ->pluck('aggregate_count', 'current_status')
            ->map(static fn ($count): int => (int) $count)
            ->all();
        ksort($counts);
        $total = array_sum($counts);
        $targetCount = $counts[$targetStatus] ?? 0;
        $mode = $total > 0 && $targetCount === $total ? 'FULL' : 'PARTIAL';
        $encodedCounts = json_encode($counts, JSON_THROW_ON_ERROR);
        $previousCounts = json_decode((string) ($consignment->parcel_status_counts ?? '{}'), true) ?: [];
        ksort($previousCounts);
        $changed = (string) $consignment->current_status !== $targetStatus
            || (string) ($consignment->aggregate_mode ?? 'FULL') !== $mode
            || $previousCounts !== $counts;

        DB::table('consignments')->where([
            'hq_id' => $actor->hqId,
            'consignment_id' => $consignmentId,
        ])->update([
            'current_status' => $targetStatus,
            'aggregate_mode' => $mode,
            'parcel_status_counts' => $encodedCounts,
            'updated_at' => now(),
        ]);

        if ($changed) {
            DB::table('consignment_status_events')->insert([
                'status_event_id' => (string) Str::uuid(),
                'hq_id' => $actor->hqId,
                'event_sequence' => ((int) DB::table('consignment_status_events')->where([
                    'hq_id' => $actor->hqId,
                    'consignment_id' => $consignmentId,
                ])->max('event_sequence')) + 1,
                'consignment_id' => $consignmentId,
                'parcel_id' => null,
                'previous_status' => $consignment->current_status,
                'new_status' => $targetStatus,
                'aggregate_mode' => $mode,
                'parcel_status_counts' => $encodedCounts,
                'initiator_id' => $actor->userId,
                'node_id' => $nodeId,
                'driver_id' => $driverId,
                'manifest_id' => $manifestId,
                'reason_code' => $reasonCode,
                'note' => "{$targetCount}/{$total}",
                'created_at' => now(),
            ]);
        }

        return [
            'status' => $targetStatus,
            'mode' => $mode,
            'parcel_counts' => $counts,
            'parcel_total' => $total,
            'target_count' => $targetCount,
        ];
    }
}
