<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\UpdateManifest;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AccessContextResolverInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Contracts\ManifestContextNormalizerInterface;
use Modules\Manifest\Application\Contracts\ManifestReaderInterface;
use Modules\Manifest\Application\Dto\ManifestDetailDto;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Manifest\Domain\Policies\ManifestPolicy;

final readonly class UpdateManifestHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ConnectionInterface $connection,
        private ManifestReaderInterface $manifestReader,
        private ManifestPolicy $manifestPolicy,
        private ManifestContextNormalizerInterface $manifestContextNormalizer,
        private AccessContextResolverInterface $accessContextResolver,
        private ClockInterface $clock,
        private AuditWriterInterface $auditWriter,
        private ManifestRepositoryInterface $manifestRepository,
        private ManifestParcelRepositoryInterface $manifestParcelRepository,
    ) {}

    public function handle(UpdateManifestCommand $command): ManifestDetailDto
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $this->connection->transaction(function () use ($actor, $nodeId, $id, $input, $correlationId): void {
            $manifest = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestPolicy->assertVersion((int) $manifest->version, $input->expectedVersion);
            $this->manifestPolicy->assertEditable($manifest->state->value);
            $mergedContext = $input->withDefaults($manifest);
            $normalized = $this->manifestContextNormalizer->normalize($actor, $nodeId, $mergedContext);
            $resolvedContext = $this->accessContextResolver->resolve($actor);
            $this->manifestAccessGuard->assertDriverVisibility($resolvedContext, (string) $normalized->status);
            $this->manifestPolicy->assertContext((string) $normalized->status, $normalized->assignedDriverId);
            if ($normalized->status !== $manifest->manifest_status && $this->manifestParcelRepository->hasRows($id)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'manifest.target_status_cannot_change_after_parcel_insertion');
            }
            $this->manifestRepository->update($id, [
                'manifest_status' => $normalized->status,
                'manifest_type' => $normalized->manifestType->value,
                'operational_context_type' => $normalized->operationalContextType->value,
                'context_key' => $normalized->contextKey,
                'origin_node_id' => $normalized->originNodeId,
                'destination_node_id' => $normalized->destinationNodeId,
                'route_plan_id' => $normalized->routePlanId,
                'route_definition_version_id' => $normalized->routeDefinitionVersionId,
                'route_plan_leg_id' => $normalized->routePlanLegId,
                'route_definition_version_leg_id' => $normalized->routeDefinitionVersionLegId,
                'source_manifest_id' => $normalized->sourceManifestId,
                'assigned_driver_id' => $normalized->assignedDriverId,
                'assigned_vehicle_id' => $normalized->assignedVehicleId,
                'version' => $manifest->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->auditWriter->write($actor->hqId, $actor->userId, 'MANIFEST_CONTEXT_UPDATED', 'MANIFEST', $id, $correlationId);
        }, attempts: 3);

        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
