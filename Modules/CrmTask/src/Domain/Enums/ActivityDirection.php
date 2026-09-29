<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum ActivityDirection: string
{
    case INBOUND = 'INBOUND';
    case OUTBOUND = 'OUTBOUND';
}
