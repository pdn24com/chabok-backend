<?php

declare(strict_types=1);

namespace Modules\Outbox\Domain\Enums;

enum PublicationState: string
{
    case PENDING = 'PENDING';
    case PUBLISHED = 'PUBLISHED';
    case FAILED = 'FAILED';
    case DEAD_LETTER = 'DEAD_LETTER';
}
