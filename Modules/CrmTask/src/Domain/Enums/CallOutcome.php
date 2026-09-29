<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

enum CallOutcome: string
{
    case ANSWERED = 'ANSWERED';
    case NO_ANSWER = 'NO_ANSWER';
    case BUSY = 'BUSY';
    case INVALID_NUMBER = 'INVALID_NUMBER';
}
