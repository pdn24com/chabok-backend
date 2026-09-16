<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCommitmentSchedule;

final readonly class ValidateCommitmentScheduleResult
{
    public function __construct(public array $data)
    {
    }
}
