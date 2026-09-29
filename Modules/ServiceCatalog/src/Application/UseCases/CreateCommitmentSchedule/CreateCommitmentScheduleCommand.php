<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

final readonly class CreateCommitmentScheduleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public CommitmentScheduleDto $input,
        public string $correlationId,
    ) {}
}
