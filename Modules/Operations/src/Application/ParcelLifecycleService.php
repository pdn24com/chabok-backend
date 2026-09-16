<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Consignment\Application\ConsignmentAggregateProjector;

final readonly class ParcelLifecycleService
{
    public function __construct(
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
        private ConsignmentAggregateProjector $aggregates,
    )
    {
    }
    /** @return list<string> parcel IDs */

    public function transition(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $from,
        string $to,
        string $command,
        ?string $nodeId,
        string $custodyType,
        ?string $custodianId,
        string $correlationId,
        ?string $driverId = null,
        ?string $manifestId = null,
        ?string $reasonCode = null,
        ?string $safeNote = null,
        ?string $routePlanId = null,
        ?string $routePlanLegId = null,
    ): array
    {
        $parcels = $this->ledger->lockParcels($actor->hqId, $consignmentId);
        if ($parcels === [] || array_filter($parcels, fn($parcel): bool => (string) $parcel->current_status !== $from) !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, "The {$from} to {$to} transition is not allowed.");
        }
        $ids = [];
        foreach ($parcels as $parcel) {
            $this->ledger->updateParcel($parcel->parcel_id, [
                'current_status' => $to,
                'current_node_id' => $nodeId,
                'current_custody_type' => $custodyType,
                'current_custodian_id' => $custodianId,
                'version' => (int) $parcel->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->ledger->appendStatusEvent([
                'status_event_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'event_sequence' => $this->ledger->nextStatusSequence($consignmentId),
                'consignment_id' => $consignmentId,
                'parcel_id' => $parcel->parcel_id,
                'previous_status' => $from,
                'new_status' => $to,
                'initiator_id' => $actor->userId,
                'node_id' => $nodeId,
                'driver_id' => $driverId,
                'manifest_id' => $manifestId,
                'correlation_id' => $correlationId,
                'reason_code' => $reasonCode ?? $command,
                'note' => $safeNote,
                'created_at' => $this->clock->now(),
            ]);
            $this->ledger->appendCustodyEvent([
                'custody_event_id' => $this->identifiers->uuid(),
                'hq_id' => $actor->hqId,
                'event_sequence' => $this->ledger->nextCustodySequence($consignmentId),
                'consignment_id' => $consignmentId,
                'parcel_id' => $parcel->parcel_id,
                'from_node_id' => $parcel->current_node_id,
                'to_node_id' => $nodeId,
                'from_custody_type' => $parcel->current_custody_type,
                'to_custody_type' => $custodyType,
                'from_custodian_id' => $parcel->current_custodian_id,
                'to_custodian_id' => $custodianId,
                'command_name' => $command,
                'initiator_id' => $actor->userId,
                'manifest_id' => $manifestId,
                'route_plan_id' => $routePlanId,
                'route_plan_leg_id' => $routePlanLegId,
                'created_at' => $this->clock->now(),
            ]);
            $ids[] = (string) $parcel->parcel_id;
        }
        $this->aggregates->project($actor, $consignmentId, $to, $nodeId, $manifestId, $reasonCode ?? $command, $driverId, $correlationId);
        $this->audit->write($actor->hqId, $actor->userId, $command, 'CONSIGNMENT', $consignmentId, $correlationId, ['status' => $from], ['status' => $to, 'custody_type' => $custodyType]);
        $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $consignmentId, 'consignment_id' => $consignmentId, 'status' => $to]);
        return $ids;
    }
}
