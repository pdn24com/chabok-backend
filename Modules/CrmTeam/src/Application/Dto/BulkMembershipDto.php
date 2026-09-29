<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Dto;

use Modules\CrmTeam\Domain\Enums\BulkMembershipOperation;

/**
 * One bulk membership run. The correlation that ties its rows together is an integer the server issues,
 * not a key the caller brings: crm_membership_events.operation_id is an integer column. Replay safety of
 * the call itself is the idempotency middleware's job, through the header it already reads.
 */
final readonly class BulkMembershipDto
{
    /** @param list<string> $userIds */
    public function __construct(
        public BulkMembershipOperation $operation,
        public array $userIds,
        public ?string $sourceTeamId = null,
        public ?string $targetTeamId = null,
        /** Who picks up the open work of the people being moved out; null leaves it where it is. */
        public ?string $replacementUserId = null,
        /** The caller has seen how much open work the run touches and accepts it. */
        public bool $confirmOpenTasks = false,
    ) {}
}
