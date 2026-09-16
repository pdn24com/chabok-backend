<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules;

final readonly class ListPublishedCommitmentSchedulesResult
{
    public function __construct(public array $data)
    {
    }
}
