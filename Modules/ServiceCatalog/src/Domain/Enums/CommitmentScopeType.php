<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentScopeType: string
{
    case Headquarters = 'HQ';
    case Node = 'NODE';
}
