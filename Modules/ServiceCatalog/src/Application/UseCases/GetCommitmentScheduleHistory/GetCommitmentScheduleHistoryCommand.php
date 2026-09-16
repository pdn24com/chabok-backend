<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetCommitmentScheduleHistoryCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $identityId)
    {
    }
}
