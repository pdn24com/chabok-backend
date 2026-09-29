<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final class ManifestSelectionDto
{
    public function __construct(
        public ?string $originNodeId = null,
        public ?string $destinationNodeId = null,
        public ?string $routePlanId = null,
        public ?string $routeDefinitionVersionId = null,
        public ?string $routePlanLegId = null,
        public ?string $routeDefinitionVersionLegId = null,
        public ?string $sourceManifestId = null,
        public ?string $assignedDriverId = null,
        public ?string $assignedVehicleId = null,
    ) {}
}
