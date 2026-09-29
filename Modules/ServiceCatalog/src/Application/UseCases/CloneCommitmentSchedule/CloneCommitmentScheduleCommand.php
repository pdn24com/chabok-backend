<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCommitmentSchedule;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CloneCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $identityId,
        public string $correlationId,
    ) {}
}
