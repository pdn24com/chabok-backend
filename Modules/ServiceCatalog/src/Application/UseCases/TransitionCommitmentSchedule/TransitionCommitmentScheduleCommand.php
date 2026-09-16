<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class TransitionCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public string $action,
        public string $correlationId,
    )
    {
    }
}
