<?php

declare(strict_types=1);

namespace Modules\Manifest\Domain\Enums;

enum ManifestParcelAddResult: string
{
    case Added = 'ADDED';
    case Failed = 'FAILED';
    case Duplicate = 'DUPLICATE';
    case NotFound = 'NOT_FOUND';
}
