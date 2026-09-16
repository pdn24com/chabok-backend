<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule;

final readonly class CloneCommitmentScheduleResult
{
    public function __construct(public array $data)
    {
    }
}
