<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule;

final readonly class TransitionCommitmentScheduleResult
{
    public function __construct(public array $data)
    {
    }
}
