<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Manifest\Domain\Enums\ManifestType;

final readonly class ManifestContextDto
{
    public function __construct(
        public string $status,
        public string $contextKey,
        public ManifestType $manifestType,
        public ManifestContextType $operationalContextType,
        public ?string $originNodeId,
        public ?string $destinationNodeId,
        public ?string $routePlanId,
        public ?string $routeDefinitionVersionId,
        public ?string $routePlanLegId,
        public ?string $routeDefinitionVersionLegId,
        public ?string $sourceManifestId,
        public ?string $assignedDriverId,
        public ?string $assignedVehicleId,
    ) {}
}
