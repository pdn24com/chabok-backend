<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\EndTeamMembership;

use DateTimeImmutable;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class EndTeamMembershipCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $membershipId,
        public ?DateTimeImmutable $validTo = null,
        /** Who picks up the open work; null leaves every task where it is. */
        public ?string $replacementUserId = null,
    ) {}
}
