<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

final readonly class ConsignmentHistoryWriter
{
    public function __construct(
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function insertStatusEvent(
        string $hqId,
        string $consignmentId,
        ?string $parcelId,
        string $actorId,
        string $nodeId,
        ?string $previous,
        string $new,
        string $reason,
        string $correlationId,
    ): void
    {
        $this->consignments->appendStatusEvent([
            'status_event_id' => $this->identifiers->uuid(),
            'event_sequence' => $this->consignments->nextStatusSequence($consignmentId),
            'hq_id' => $hqId,
            'consignment_id' => $consignmentId,
            'parcel_id' => $parcelId,
            'previous_status' => $previous,
            'new_status' => $new,
            'initiator_id' => $actorId,
            'node_id' => $nodeId,
            'correlation_id' => $correlationId,
            'reason_code' => $reason,
            'created_at' => $this->clock->now(),
        ]);
    }
}
