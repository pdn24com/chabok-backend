<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ManifestTransitionRecorder
{
    public function __construct(
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Consignment\Application\Repositories\ConsignmentLedgerRepository $ledger,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function recordTransition(AuthenticatedPrincipal $actor, string $node, object $m, object $p, array $e, string $correlationId): void
    {
        $this->statusEvent($actor, $node, (string) $p->consignment_id, (string) $p->parcel_id, (string) $p->current_status, (string) $m->manifest_status, (string) $m->manifest_id, $correlationId);
        $toCustody = match ((string) $m->manifest_status) {
            'PD', 'PU', 'NPU' => 'PICKUP_DRIVER',
            'OS' => 'LINEHAUL_DRIVER',
            'OD', 'NOK' => 'DELIVERY_DRIVER',
            'OK' => 'RECIPIENT',
            default => 'NODE',
        };
        $toCustodian = match ($toCustody) {
            'PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER' => $m->assigned_driver_id,
            'NODE' => $node,
            default => null,
        };
        $toNode = $toCustody === 'NODE' ? $node : null;
        $this->consignmentState->appendCustodyEvent([
            'custody_event_id' => $this->identifiers->uuid(),
            'hq_id' => $actor->hqId,
            'event_sequence' => $this->ledger->nextCustodySequence((string) $p->consignment_id),
            'consignment_id' => $p->consignment_id,
            'parcel_id' => $p->parcel_id,
            'from_node_id' => $p->current_node_id,
            'to_node_id' => $toNode,
            'from_custody_type' => $p->current_custody_type,
            'to_custody_type' => $toCustody,
            'from_custodian_id' => $p->current_custodian_id,
            'to_custodian_id' => $toCustodian,
            'command_name' => 'MANIFEST_' . (string) $m->manifest_status,
            'initiator_id' => $actor->userId,
            'manifest_id' => $m->manifest_id,
            'route_plan_id' => $e['route_plan_id'],
            'route_plan_leg_id' => $e['route_plan_leg_id'],
            'created_at' => $this->clock->now(),
        ]);
    }

    public function statusEvent(
        AuthenticatedPrincipal $actor,
        string $node,
        string $consignment,
        ?string $parcel,
        string $old,
        string $new,
        string $manifest,
        string $correlationId,
    ): void
    {
        $this->consignmentState->appendStatusEvent([
            'status_event_id' => $this->identifiers->uuid(),
            'hq_id' => $actor->hqId,
            'event_sequence' => $this->ledger->nextStatusSequence($consignment),
            'consignment_id' => $consignment,
            'parcel_id' => $parcel,
            'previous_status' => $old,
            'new_status' => $new,
            'initiator_id' => $actor->userId,
            'node_id' => $node,
            'manifest_id' => $manifest,
            'correlation_id' => $correlationId,
            'reason_code' => 'MANIFEST_CONFIRMED',
            'created_at' => $this->clock->now(),
        ]);
    }

    public function commandEvent(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $consignment,
        string $command,
        string $status,
        string $correlationId,
    ): void
    {
        $this->outbox->write($actor->hqId, 'MANIFEST', $resource, 'operations.command.executed', $correlationId, ['command' => $command, 'resource_id' => $resource, 'consignment_id' => $consignment, 'status' => $status]);
    }
}
