<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestTransitionRecorderInterface;
use Modules\Manifest\Application\Dto\ManifestParcelTransitionDto;
use Modules\Manifest\Application\Repositories\ManifestEvidenceRepositoryInterface;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ManifestTransitionRecorder implements ManifestTransitionRecorderInterface
{
    public function __construct(
        private ClockInterface $clock,
        private OutboxWriterInterface $outboxWriter,
        private ManifestEvidenceRepositoryInterface $manifestEvidenceRepository,
    ) {}

    /**
     * Caller owns the transaction and locks each consignment before its parcels.
     *
     * @param  list<ManifestParcelTransitionDto>  $transitions  Original parcel snapshots, before mutation.
     */
    public function recordTransitions(AuthenticatedPrincipal $actor, string $node, ManifestRecord $manifest, array $transitions, string $correlationId): void
    {
        if ($transitions === []) {
            return;
        }
        $consignmentIds = array_values(array_unique(array_map(fn ($transition) => $transition->previousParcel->consignment_id, $transitions)));
        // SQL aggregation reads the sequence heads once for the whole batch.
        $statusSequences = $this->manifestEvidenceRepository->statusSequenceHeads($actor->hqId, $consignmentIds);
        $custodySequences = $this->manifestEvidenceRepository->custodySequenceHeads($actor->hqId, $consignmentIds);
        $target = (string) $manifest->manifest_status;
        $toCustody = match ($target) {
            'PD', 'PU', 'NPU' => 'PICKUP_DRIVER',
            'OS' => 'LINEHAUL_DRIVER',
            'OD', 'NOK' => 'DELIVERY_DRIVER',
            'OK' => 'RECIPIENT',
            default => 'NODE',
        };
        $toCustodian = match ($toCustody) {
            'PICKUP_DRIVER', 'LINEHAUL_DRIVER', 'DELIVERY_DRIVER' => $manifest->assigned_driver_id,
            'NODE' => $node,
            default => null,
        };
        $statuses = $custodies = [];
        $now = $this->clock->now();
        foreach ($transitions as $transition) {
            $parcel = $transition->previousParcel;
            $consignment = $parcel->consignment_id;
            $statusSequences[$consignment] = (int) ($statusSequences[$consignment] ?? 0) + 1;
            $custodySequences[$consignment] = (int) ($custodySequences[$consignment] ?? 0) + 1;
            $statuses[] = [

                'hq_id' => $actor->hqId,
                'event_sequence' => $statusSequences[$consignment],
                'consignment_id' => $consignment,
                'parcel_id' => $parcel->parcel_id,
                'previous_status' => $parcel->current_status,
                'new_status' => $target,
                'initiator_id' => $actor->userId,
                'node_id' => $node,
                'manifest_id' => $manifest->manifest_id,
                'correlation_id' => $correlationId,
                'reason_code' => 'MANIFEST_CONFIRMED',
                'created_at' => $now,
            ];
            $custodies[] = [

                'hq_id' => $actor->hqId,
                'event_sequence' => $custodySequences[$consignment],
                'consignment_id' => $consignment,
                'parcel_id' => $parcel->parcel_id,
                'from_node_id' => $parcel->current_node_id,
                'to_node_id' => $toCustody === 'NODE' ? $node : null,
                'from_custody_type' => $parcel->current_custody_type,
                'to_custody_type' => $toCustody,
                'from_custodian_id' => $parcel->current_custodian_id,
                'to_custodian_id' => $toCustodian,
                'command_name' => 'MANIFEST_'.$target,
                'initiator_id' => $actor->userId,
                'manifest_id' => $manifest->manifest_id,
                'route_plan_id' => $transition->evidence->routePlanId,
                'route_plan_leg_id' => $transition->evidence->routePlanLegId,
                'created_at' => $now,
            ];
        }
        foreach (array_chunk($statuses, 100) as $chunk) {
            $this->manifestEvidenceRepository->insertStatusEvents($chunk);
        }
        foreach (array_chunk($custodies, 100) as $chunk) {
            $this->manifestEvidenceRepository->insertCustodyEvents($chunk);
        }
    }

    public function commandEvent(
        AuthenticatedPrincipal $actor,
        string $resource,
        string $consignment,
        string $command,
        string $status,
        string $correlationId,
    ): void {
        $this->outboxWriter->write($actor->hqId, 'MANIFEST', $resource, 'operations.command.executed', $correlationId, [
            'command' => $command,
            'resource_id' => $resource,
            'consignment_id' => $consignment,
            'status' => $status,
        ]);
    }
}
