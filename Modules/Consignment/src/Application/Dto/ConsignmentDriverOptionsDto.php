<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Illuminate\Database\Eloquent\Collection;

/** Drivers that actually appear on the Consignments a caller can see, for the list filter row. */
final readonly class ConsignmentDriverOptionsDto
{
    /** @param Collection<int, \Modules\Operations\Infrastructure\Persistence\Models\DriverRecord> $pickupDrivers @param Collection<int, \Modules\Operations\Infrastructure\Persistence\Models\DriverRecord> $deliveryDrivers */
    public function __construct(
        public Collection $pickupDrivers,
        public Collection $deliveryDrivers,
    ) {}
}
