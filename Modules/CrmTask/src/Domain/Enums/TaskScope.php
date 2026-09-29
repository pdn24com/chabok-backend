<?php

declare(strict_types=1);

namespace Modules\CrmTask\Domain\Enums;

/** Whose tasks the inbox shows: only the actor's own, or every task the actor may read. */
enum TaskScope: string
{
    case MINE = 'mine';
    case ACCESSIBLE = 'accessible';
}
