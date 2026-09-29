<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;

final readonly class GetConsignmentFilterOptionsResult
{
    /** @param Collection<int, DriverRecord> $pickupDrivers @param Collection<int, DriverRecord> $deliveryDrivers */
    public function __construct(public Collection $pickupDrivers, public Collection $deliveryDrivers) {}
}
