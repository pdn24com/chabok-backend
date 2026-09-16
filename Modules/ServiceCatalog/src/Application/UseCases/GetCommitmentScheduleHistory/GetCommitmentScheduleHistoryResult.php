<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory;

final readonly class GetCommitmentScheduleHistoryResult
{
    public function __construct(public array $data)
    {
    }
}
