<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\CreateManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateManifestHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestAccessGuard $manifestAccessGuard,
        private \Modules\Manifest\Application\ManifestOperationalContext $operationalContext,
        private \Modules\Manifest\Domain\ManifestPolicy $policy,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Manifest\Application\Services\ManifestNumberAllocator $numbers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
        private \Modules\Manifest\Application\Services\ManifestReader $manifestReader,
    )
    {
    }

    public function handle(CreateManifestCommand $command): CreateManifestResult
    {
        return new CreateManifestResult($this->execute($command->actor, $command->nodeId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $input, string $correlationId): array
    {
        $context = $this->manifestAccessGuard->access($actor, $nodeId, 'manifest.create');
        $normalized = $this->operationalContext->normalize($actor, $nodeId, $input);
        $this->manifestAccessGuard->assertDriverVisibility($context, (string) $normalized['manifest_status']);
        $this->policy->assertContext((string) $normalized['manifest_status'], $normalized['assigned_driver_id']);
        $id = $this->transactions->run(function () use ($actor, $nodeId, $normalized, $correlationId): string {
            $id = $this->identifiers->uuid();
            $this->manifests->insert([
                'manifest_id' => $id,
                'hq_id' => $actor->hqId,
                'manifest_number' => $this->numbers->next(),
                'node_id' => $nodeId,
                'manifest_status' => $normalized['manifest_status'],
                'context_key' => $normalized['context_key'],
                'manifest_type' => $normalized['manifest_type'],
                'operational_context_type' => $normalized['operational_context_type'],
                'origin_node_id' => $normalized['origin_node_id'],
                'destination_node_id' => $normalized['destination_node_id'],
                'route_plan_id' => $normalized['route_plan_id'],
                'route_definition_version_id' => $normalized['route_definition_version_id'],
                'route_plan_leg_id' => $normalized['route_plan_leg_id'],
                'route_definition_version_leg_id' => $normalized['route_definition_version_leg_id'],
                'source_manifest_id' => $normalized['source_manifest_id'],
                'assigned_driver_id' => $normalized['assigned_driver_id'],
                'assigned_vehicle_id' => $normalized['assigned_vehicle_id'],
                'state' => 'DRAFT',
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->audit->write($actor->hqId, $actor->userId, 'MANIFEST_CREATED', 'MANIFEST', $id, $correlationId);
            $this->outbox->write($actor->hqId, 'MANIFEST', $id, 'manifest.created', $correlationId, ['manifest_id' => $id, 'manifest_status' => $normalized['manifest_status'], 'version' => '1']);
            return $id;
        });
        return $this->manifestReader->detail($actor, $nodeId, $id, $context);
    }
}
