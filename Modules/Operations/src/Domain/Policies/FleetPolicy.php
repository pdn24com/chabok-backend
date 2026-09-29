<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Policies;

use Modules\Operations\Domain\Enums\DriverCapability;
use Modules\Operations\Domain\Enums\DriverOperationalType;
use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;
use Modules\Operations\Domain\Exceptions\InvalidFleetConfiguration;
use Modules\Operations\Domain\ValueObjects\FleetLifecycle;

final readonly class FleetPolicy
{
    /** @param list<DriverCapability> $values @return list<DriverCapability> */
    public function capabilities(array $values): array
    {
        $values = array_values(array_unique($values, SORT_REGULAR));
        usort($values, static fn (DriverCapability $left, DriverCapability $right) => $left->value <=> $right->value);
        if ($values === []) {
            throw new InvalidFleetConfiguration('operations.at_least_one_valid_driver_capability_is_required', ['capabilities' => ['operations.valid_capability_is_required']]);
        }

        return $values;
    }

    /** @param list<DriverCapability> $capabilities */
    public function operationalType(array $capabilities): DriverOperationalType
    {
        return count($capabilities) === 1 ? DriverOperationalType::from($capabilities[0]->value) : DriverOperationalType::Multi;
    }

    public function lifecycle(FleetStatus $status, FleetAvailability $availability, bool $statusWasProvided): FleetLifecycle
    {
        if ($statusWasProvided && $status === FleetStatus::Inactive) {
            $availability = FleetAvailability::Inactive;
        }
        if ($statusWasProvided && $status === FleetStatus::Active && $availability === FleetAvailability::Inactive) {
            $availability = FleetAvailability::Available;
        }
        if (($status === FleetStatus::Inactive) !== ($availability === FleetAvailability::Inactive)) {
            throw new InvalidFleetConfiguration('operations.inactive_fleet_must_have_inactive_availability');
        }

        return new FleetLifecycle($status, $availability);
    }

    public function nullableString(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
