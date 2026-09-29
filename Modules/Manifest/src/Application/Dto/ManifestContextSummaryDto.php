<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Domain\Enums\ManifestContextType;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class ManifestContextSummaryDto
{
    public function __construct(
        public ManifestContextType $type,
        public ?NodeRecord $issuingNode,
        public ?NodeRecord $targetNode,
        public ?NodeRecord $currentNode,
        public ?NodeRecord $relatedNode,
        public string $relatedNodeRole,
        public ?NodeRecord $originNode,
        public ?NodeRecord $destinationNode,
        public ?RoutePlanRecord $routePlan,
        public ?RoutePlanLegRecord $routeLeg,
        public ?DriverRecord $driver,
        public ?VehicleRecord $vehicle,
    ) {}
}
