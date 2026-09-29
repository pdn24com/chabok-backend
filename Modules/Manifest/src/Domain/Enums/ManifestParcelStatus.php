<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestParcelStatus: string
{
    case Pending = 'PENDING';
    case Validated = 'VALIDATED';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';
}
