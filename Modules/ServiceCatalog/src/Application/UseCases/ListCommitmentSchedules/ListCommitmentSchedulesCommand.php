<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCommitmentSchedules;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListCommitmentSchedulesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
