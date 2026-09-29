<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Domain\Enums\MovementCommand;
use Modules\Operations\Domain\Enums\MovementEntityType;
use Modules\Operations\Domain\Enums\RoutePlanStatus;

interface MovementRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, MovementCommand $command, MovementEntityType $type, string $id, string $consignmentId, RoutePlanStatus $status, string $correlationId): void;
}
