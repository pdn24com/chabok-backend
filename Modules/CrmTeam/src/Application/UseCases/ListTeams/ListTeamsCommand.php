<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\ListTeams;

use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListTeamsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public ?TeamStatus $status = null) {}
}
