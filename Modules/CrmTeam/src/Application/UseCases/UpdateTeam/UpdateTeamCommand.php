<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\UpdateTeam;

use Modules\CrmTeam\Application\Dto\TeamChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateTeamCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $teamId,
        public TeamChangesDto $changes,
    ) {}
}
