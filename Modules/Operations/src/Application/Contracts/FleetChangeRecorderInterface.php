<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Infrastructure\Persistence\Models\DriverRecord;
use Modules\Operations\Infrastructure\Persistence\Models\VehicleRecord;

interface FleetChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $status, string $correlationId, DriverRecord|VehicleRecord|null $before, DriverRecord|VehicleRecord $after): void;
}
