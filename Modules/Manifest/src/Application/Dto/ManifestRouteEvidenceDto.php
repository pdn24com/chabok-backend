<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestRouteEvidenceDto
{
    public function __construct(
        public ?string $originNodeId,
        public ?string $destinationNodeId,
        public ?string $routePlanId,
        public ?string $routeDefinitionVersionId,
        public ?string $routePlanLegId,
        public ?string $routeDefinitionVersionLegId,
        public ?string $assignedDriverId,
        public ?string $assignedVehicleId,
    ) {}
}
