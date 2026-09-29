<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Enums;

enum RouteDefinitionLegStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';
}
