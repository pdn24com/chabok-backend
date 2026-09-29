<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\CreateManifest;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestContextNormalizerInterface;
use Modules\Manifest\Application\Contracts\ManifestNumberAllocatorInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Domain\Enums\ManifestState;
use Modules\Manifest\Domain\Policies\ManifestPolicy;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class CreateManifestHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestContextNormalizerInterface $manifestContextNormalizer,
        private ManifestPolicy $manifestPolicy,
        private ConnectionInterface $connection,
        private ManifestNumberAllocatorInterface $manifestNumberAllocator,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private ManifestReaderInterface $manifestReader,
    ) {}

    public function handle(CreateManifestCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.create');
        $normalized = $this->manifestContextNormalizer->normalize($actor, $nodeId, $input);
        $this->manifestAccessGuard->assertDriverVisibility($context, (string) $normalized->status);
        $this->manifestPolicy->assertContext((string) $normalized->status, $normalized->assignedDriverId);
        $id = $this->connection->transaction(function () use ($actor, $nodeId, $normalized, $correlationId): string {
            $id = (string) ManifestRecord::query()->forceCreate([

                'hq_id' => $actor->hqId,
                'manifest_number' => $this->manifestNumberAllocator->next(),
                'node_id' => $nodeId,
                'manifest_status' => $normalized->status,
                'context_key' => $normalized->contextKey,
                'manifest_type' => $normalized->manifestType->value,
                'operational_context_type' => $normalized->operationalContextType->value,
                'origin_node_id' => $normalized->originNodeId,
                'destination_node_id' => $normalized->destinationNodeId,
                'route_plan_id' => $normalized->routePlanId,
                'route_definition_version_id' => $normalized->routeDefinitionVersionId,
                'route_plan_leg_id' => $normalized->routePlanLegId,
                'route_definition_version_leg_id' => $normalized->routeDefinitionVersionLegId,
                'source_manifest_id' => $normalized->sourceManifestId,
                'assigned_driver_id' => $normalized->assignedDriverId,
                'assigned_vehicle_id' => $normalized->assignedVehicleId,
                'state' => ManifestState::Draft->value,
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->getKey();
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_CREATED', 'MANIFEST', $id, $correlationId);
            $this->outboxWriter->write($actor->hqId, 'MANIFEST', $id, 'manifest.created', $correlationId, [
                'manifest_id' => $id,
                'manifest_status' => $normalized->status,
                'version' => '1',
            ]);

            return $id;
        }, attempts: 3);

        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
