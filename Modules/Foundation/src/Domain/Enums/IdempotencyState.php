<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

enum IdempotencyState: string
{
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
}
