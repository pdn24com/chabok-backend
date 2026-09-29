<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCommitmentSchedule;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

final readonly class UpdateCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public CommitmentScheduleDto $input,
        public string $correlationId,
    ) {}
}
