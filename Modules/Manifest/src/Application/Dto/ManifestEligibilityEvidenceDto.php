<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanLegRecord;

/** A request-local snapshot; never reused after operational writes. */
final readonly class ManifestEligibilityEvidenceDto
{
    /**
     * @param  Collection<string, ConsignmentRecord>  $consignments
     * @param  Collection<string, PickupTaskRecord>  $pickups
     * @param  Collection<string, DeliveryTaskRecord>  $deliveries
     * @param  Collection<string, ManifestParcelRecord>  $sourceRows
     * @param  Collection<string, RoutePlanLegRecord>  $legs
     */
    public function __construct(
        public Collection $consignments,
        public Collection $pickups,
        public Collection $deliveries,
        public Collection $sourceRows,
        public Collection $legs,
    ) {}
}
