<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\NormalizeManifestContext;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class NormalizeManifestContextHandler
{
    public function __construct(
        private \Modules\Manifest\Application\Services\ManifestContextReferences $manifestContextReferences,
        private \Modules\Manifest\Application\Services\ManifestContextOptions $manifestContextOptions,
        private \Modules\Manifest\Application\Services\ManifestContextAccess $manifestContextAccess,
        private \Modules\Manifest\Domain\ManifestContextShape $manifestContextShape,
    )
    {
    }

    public function handle(NormalizeManifestContextCommand $command): NormalizeManifestContextResult
    {
        return new NormalizeManifestContextResult($this->execute($command->actor, $command->nodeId, $command->input));
    }

    private function execute(AuthenticatedPrincipal $actor, string $nodeId, array $input): array
    {
        $target = (string) ($input['manifest_status'] ?? '');
        $contextKey = (string) ($input['context_key'] ?? '');
        $node = $this->manifestContextReferences->node((string) $actor->hqId, $nodeId);
        $option = array_values(array_filter($this->manifestContextOptions->operationalOptions($actor, $nodeId, $node), fn(array $candidate): bool => $candidate['manifest_status'] === $target && hash_equals((string) $candidate['context_key'], $contextKey)))[0] ?? null;
        if (!is_array($option)) {
            throw new ApiException(ApiErrorCode::UnsupportedManifestTransition, 422, 'The selected server context is unavailable.');
        }
        if (isset($input['target_node_id'])) {
            $targetNodeId = (string) $input['target_node_id'];
            $this->manifestContextAccess->assertTargetNode($actor, $targetNodeId);
            if (!hash_equals((string) ($option['resolution']['destination_node_id'] ?? ''), $targetNodeId)) {
                throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'The selected target Node does not match the server context.');
            }
        }
        $normalized = $option['resolution'];
        $normalized['manifest_status'] = $target;
        $normalized['context_key'] = $contextKey;
        $normalized['manifest_type'] = $this->manifestContextShape->manifestType($target);
        $normalized['operational_context_type'] = $option['operational_context_type'];
        $requiredCapability = match ($target) {
            'PD' => 'PICKUP',
            'OD' => 'DELIVERY',
            'OS' => 'LINEHAUL',
            default => null,
        };
        if ($requiredCapability !== null) {
            $driverId = isset($input['assigned_driver_id']) ? (string) $input['assigned_driver_id'] : '';
            $this->manifestContextAccess->assertDriver($actor, $driverId, $requiredCapability);
            $normalized['assigned_driver_id'] = $driverId;
        }
        if ($target === 'OS') {
            $vehicleId = isset($input['assigned_vehicle_id']) ? (string) $input['assigned_vehicle_id'] : '';
            $this->manifestContextAccess->assertVehicle((string) $actor->hqId, $nodeId, $vehicleId);
            $normalized['assigned_vehicle_id'] = $vehicleId;
        }
        if (!in_array($target, ['PD', 'OD', 'OS'], true) && array_key_exists('assigned_driver_id', $input) && $input['assigned_driver_id'] !== null && (string) $input['assigned_driver_id'] !== (string) ($normalized['assigned_driver_id'] ?? '')) {
            throw new ApiException(ApiErrorCode::DriverOutOfScope, 422, 'The Driver is derived from the selected server context.');
        }
        return $normalized;
    }
}
