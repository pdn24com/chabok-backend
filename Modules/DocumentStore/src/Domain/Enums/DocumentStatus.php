<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Domain\Enums;

/** A document is never deleted; it is archived, so what it was once attached to still reads. */
enum DocumentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ARCHIVED = 'ARCHIVED';
}
