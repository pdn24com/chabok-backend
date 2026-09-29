<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\AddTeamMember;

use DateTimeImmutable;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AddTeamMemberCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $teamId,
        public string $userId,
        /** Left out, the membership starts now. */
        public ?DateTimeImmutable $validFrom = null,
    ) {}
}
