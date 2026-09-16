<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule;

final readonly class UpdateCommitmentScheduleResult
{
    public function __construct(public array $data)
    {
    }
}
