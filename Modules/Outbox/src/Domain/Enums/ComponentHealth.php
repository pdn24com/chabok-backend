<?php

declare(strict_types=1);

namespace Modules\Outbox\Domain\Enums;

enum ComponentHealth: string
{
    case UP = 'UP';
    case DOWN = 'DOWN';
    case STALE = 'STALE';
}
