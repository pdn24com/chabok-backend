<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

/** Client surface an audited action was initiated from. */
enum SourceClient: string
{
    case BranchPanel = 'BRANCH_PANEL';
}
