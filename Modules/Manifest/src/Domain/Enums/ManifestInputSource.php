<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestInputSource: string
{
    case Scan = 'SCAN';
    case Manual = 'MANUAL';
    case Batch = 'BATCH';
    case Awaiting = 'AWAITING';
}
