<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules;

use Modules\Foundation\Application\Data\Page;

final readonly class ListCommitmentSchedulesResult
{
    public function __construct(public Page $data)
    {
    }
}
