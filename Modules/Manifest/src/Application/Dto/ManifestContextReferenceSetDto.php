<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

final readonly class ManifestContextReferenceSetDto
{
    /**
     * @param  Collection<string, NodeRecord>  $nodes
     * @param  Collection<string, DriverRecord>  $drivers
     * @param  Collection<string, VehicleRecord>  $vehicles
     * @param  Collection<string, RoutePlanRecord>  $plans
     * @param  Collection<string, RoutePlanLegRecord>  $legs
     */
    public function __construct(
        public Collection $nodes,
        public Collection $drivers,
        public Collection $vehicles,
        public Collection $plans,
        public Collection $legs,
    ) {}
}
