<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

/** Audit action recorded for an operational movement command. */
enum MovementCommand: string
{
    case RoutePlanCreated = 'ROUTE_PLAN_CREATED';
}
