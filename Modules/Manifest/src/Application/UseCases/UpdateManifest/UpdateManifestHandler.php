<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\UpdateManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateManifestHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
        private \Modules\Manifest\Domain\ManifestVersionGuard $manifestVersionGuard,
        private \Modules\Manifest\Domain\ManifestPolicy $policy,
        private \Modules\Manifest\Application\ManifestOperationalContext $operationalContext,
        private \Modules\Foundation\Application\Contracts\AuthorizationContextResolver $authorization,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
    )
    {
    }

    public function handle(UpdateManifestCommand $command): UpdateManifestResult
    {
        return new UpdateManifestResult($this->execute($command->actor, $command->nodeId, $command->id, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $input, string $correlationId): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.edit');
        $this->transactions->run(function () use ($actor, $nodeId, $id, $input, $correlationId): void {
            $manifest = $this->manifestReader->locked($actor, $nodeId, $id);
            $this->manifestVersionGuard->version($manifest, (int) $input['expected_version']);
            $this->policy->assertEditable((string) $manifest->state);
            $mergedContext = [
                'manifest_status' => (string) ($input['manifest_status'] ?? $manifest->manifest_status),
                'context_key' => (string) ($input['context_key'] ?? $manifest->context_key),
                'target_node_id' => array_key_exists('target_node_id', $input) ? $input['target_node_id'] : $manifest->destination_node_id,
                'assigned_driver_id' => array_key_exists('assigned_driver_id', $input) ? $input['assigned_driver_id'] : $manifest->assigned_driver_id,
                'assigned_vehicle_id' => array_key_exists('assigned_vehicle_id', $input) ? $input['assigned_vehicle_id'] : $manifest->assigned_vehicle_id,
            ];
            $normalized = $this->operationalContext->normalize($actor, $nodeId, $mergedContext);
            $resolvedContext = $this->authorization->resolve($actor);
            $this->manifestAccessGuard->assertDriverVisibility($resolvedContext, (string) $normalized['manifest_status']);
            $this->policy->assertContext((string) $normalized['manifest_status'], $normalized['assigned_driver_id']);
            if ($normalized['manifest_status'] !== $manifest->manifest_status && $this->manifests->hasParcels($id)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Target status cannot change after Parcel insertion.');
            }
            $this->manifests->update($id, [
                'manifest_status' => $normalized['manifest_status'],
                'manifest_type' => $normalized['manifest_type'],
                'operational_context_type' => $normalized['operational_context_type'],
                'context_key' => $normalized['context_key'],
                'origin_node_id' => $normalized['origin_node_id'],
                'destination_node_id' => $normalized['destination_node_id'],
                'route_plan_id' => $normalized['route_plan_id'],
                'route_definition_version_id' => $normalized['route_definition_version_id'],
                'route_plan_leg_id' => $normalized['route_plan_leg_id'],
                'route_definition_version_leg_id' => $normalized['route_definition_version_leg_id'],
                'source_manifest_id' => $normalized['source_manifest_id'],
                'assigned_driver_id' => $normalized['assigned_driver_id'],
                'assigned_vehicle_id' => $normalized['assigned_vehicle_id'],
                'version' => $manifest->version + 1,
                'updated_at' => $this->clock->now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CONTEXT_UPDATED', 'MANIFEST', $id, $correlationId);
        });
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
