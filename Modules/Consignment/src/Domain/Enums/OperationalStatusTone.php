<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Enums;

enum OperationalStatusTone: string
{
    case Neutral = 'neutral';
    case Info = 'info';
    case Brand = 'brand';
    case Warning = 'warning';
    case Danger = 'danger';
    case Success = 'success';
    case Ink = 'ink';
}
