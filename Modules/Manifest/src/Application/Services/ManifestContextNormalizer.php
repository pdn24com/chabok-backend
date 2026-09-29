<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Contracts\ManifestContextAccessInterface;
use Modules\Manifest\Application\Contracts\ManifestContextNormalizerInterface;
use Modules\Manifest\Application\Contracts\ManifestContextOptionsInterface;
use Modules\Manifest\Application\Contracts\ManifestContextReferencesInterface;
use Modules\Manifest\Application\Dto\ManifestContextDto;
use Modules\Manifest\Application\Dto\ManifestContextInputDto;
use Modules\Manifest\Domain\Enums\ManifestTransition;

final readonly class ManifestContextNormalizer implements ManifestContextNormalizerInterface
{
    public function __construct(
        private ManifestContextReferencesInterface $manifestContextReferences,
        private ManifestContextOptionsInterface $manifestContextOptions,
        private ManifestContextAccessInterface $manifestContextAccess,
    ) {}

    public function normalize(AuthenticatedPrincipal $actor, string $nodeId, ManifestContextInputDto $input): ManifestContextDto
    {
        $target = (string) ($input->status ?? '');
        $contextKey = (string) ($input->contextKey ?? '');
        $node = $this->manifestContextReferences->node((string) $actor->hqId, $nodeId);
        $option = null;
        foreach ($this->manifestContextOptions->operationalOptions($actor, $nodeId, $node) as $candidate) {
            if ($candidate->status === $target && hash_equals($candidate->key, $contextKey)) {
                $option = $candidate;
                break;
            }
        }
        if ($option === null) {
            throw new ApiException(ApiErrorCode::UnsupportedManifestTransition, 422, 'manifest.selected_server_context_is_unavailable');
        }
        if ($input->targetNodeId !== null) {
            $targetNodeId = (string) $input->targetNodeId;
            $this->manifestContextAccess->assertTargetNode($actor, $targetNodeId);
            if (! hash_equals((string) ($option->selection->destinationNodeId ?? ''), $targetNodeId)) {
                throw new ApiException(ApiErrorCode::CurrentNodeMismatch, 422, 'manifest.selected_target_node_does_not_match_server');
            }
        }
        $normalized = clone $option->selection;
        $requiredCapability = ManifestTransition::from($target)->requiredDriverCapability();
        if ($requiredCapability !== null) {
            $driverId = $input->assignedDriverId ?? '';
            $this->manifestContextAccess->assertDriver($actor, $driverId, $requiredCapability);
            $normalized->assignedDriverId = $driverId;
        }
        if ($target === 'OS') {
            $vehicleId = $input->assignedVehicleId ?? '';
            $this->manifestContextAccess->assertVehicle((string) $actor->hqId, $nodeId, $vehicleId);
            $normalized->assignedVehicleId = $vehicleId;
        }
        if (! in_array($target, ['PD', 'OD', 'OS'], true) && $input->driverProvided && $input->assignedDriverId !== null && (string) $input->assignedDriverId !== (string) ($normalized->assignedDriverId ?? '')) {
            throw new ApiException(ApiErrorCode::DriverOutOfScope, 422, 'manifest.driver_is_derived_from_selected_server_context');
        }

        return new ManifestContextDto(
            status: $target,
            contextKey: $contextKey,
            manifestType: ManifestTransition::from($target)->manifestType(),
            operationalContextType: $option->type,
            originNodeId: $normalized->originNodeId,
            destinationNodeId: $normalized->destinationNodeId,
            routePlanId: $normalized->routePlanId,
            routeDefinitionVersionId: $normalized->routeDefinitionVersionId,
            routePlanLegId: $normalized->routePlanLegId,
            routeDefinitionVersionLegId: $normalized->routeDefinitionVersionLegId,
            sourceManifestId: $normalized->sourceManifestId,
            assignedDriverId: $normalized->assignedDriverId,
            assignedVehicleId: $normalized->assignedVehicleId,
        );
    }
}
