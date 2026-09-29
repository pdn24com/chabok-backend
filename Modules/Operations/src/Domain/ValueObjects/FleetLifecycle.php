<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\ValueObjects;

use Modules\Operations\Domain\Enums\FleetAvailability;
use Modules\Operations\Domain\Enums\FleetStatus;

final readonly class FleetLifecycle
{
    public function __construct(public FleetStatus $status, public FleetAvailability $availability) {}
}
