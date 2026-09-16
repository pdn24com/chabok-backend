<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
