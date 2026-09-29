<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum MeetingMode: string
{
    case IN_PERSON = 'IN_PERSON';
    case ONLINE = 'ONLINE';
}
