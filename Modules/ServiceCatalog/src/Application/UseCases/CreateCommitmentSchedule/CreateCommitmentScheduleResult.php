<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule;

final readonly class CreateCommitmentScheduleResult
{
    public function __construct(public array $data)
    {
    }
}
