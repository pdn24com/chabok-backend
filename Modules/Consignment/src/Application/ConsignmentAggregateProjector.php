<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final class ConsignmentAggregateProjector
{
    public function __construct(
        private readonly \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private readonly \Modules\Foundation\Application\Contracts\Clock $clock,
        private readonly \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }
    /** @return array{status:string,mode:string,parcel_counts:array<string,int>,parcel_total:int,target_count:int} */

    public function project(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $targetStatus,
        ?string $nodeId,
        ?string $manifestId,
        string $reasonCode,
        ?string $driverId = null,
        ?string $correlationId = null,
    ): array
    {
        $consignment = $this->ledger->lockConsignment($actor->hqId, $consignmentId);
        if ($consignment === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $counts = $this->ledger->parcelStatusCounts($actor->hqId, $consignmentId);
        ksort($counts);
        $total = array_sum($counts);
        $targetCount = $counts[$targetStatus] ?? 0;
        $mode = $total > 0 && $targetCount === $total ? 'FULL' : 'PARTIAL';
        $encodedCounts = json_encode($counts, JSON_THROW_ON_ERROR);
        $previousCounts = json_decode((string) ($consignment->parcel_status_counts ?? '{}'), true) ?: [];
        ksort($previousCounts);
        $changed = (string) $consignment->current_status !== $targetStatus || (string) ($consignment->aggregate_mode ?? 'FULL') !== $mode || $previousCounts !== $counts;
        $this->ledger->updateConsignment($actor->hqId, $consignmentId, [
            'current_status' => $targetStatus,
            'aggregate_mode' => $mode,
            'parcel_status_counts' => $encodedCounts,
            'updated_at' => $this->clock->now(),
        ]);
        if ($changed) {
            $this->ledger->appendStatusEvent([
                'status_event_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'event_sequence' => $this->ledger->nextStatusSequence($consignmentId, $actor->hqId),
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
                'correlation_id' => $correlationId,
                'reason_code' => $reasonCode,
                'note' => "{$targetCount}/{$total}",
                'created_at' => $this->clock->now(),
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
