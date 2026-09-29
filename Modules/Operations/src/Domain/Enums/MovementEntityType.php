<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

/** Audited entity type an operational movement command acts on. */
enum MovementEntityType: string
{
    case RoutePlan = 'ROUTE_PLAN';
}
