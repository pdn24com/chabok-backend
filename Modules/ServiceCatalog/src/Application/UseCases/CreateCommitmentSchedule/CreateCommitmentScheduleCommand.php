<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCommitmentSchedule;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateCommitmentScheduleCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
