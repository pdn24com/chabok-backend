<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\CreateTeam;

use Modules\CrmTeam\Application\Dto\TeamDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateTeamCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public TeamDraftDto $input) {}
}
