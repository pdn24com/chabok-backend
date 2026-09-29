<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\Enums;

enum CommitmentMode: string
{
    case None = 'NONE';
    case SelectableWindow = 'SELECTABLE_WINDOW';
    case Computed = 'COMPUTED';
}
