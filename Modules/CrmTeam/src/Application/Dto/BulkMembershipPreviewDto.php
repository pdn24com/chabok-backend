<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Dto;

use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

/** What a bulk run would touch, so the operator sees the open work before committing to it. */
final readonly class BulkMembershipPreviewDto
{
    /**
     * @param  list<TeamMemberRecord>  $memberships
     * @param  list<array{task_id: string, title: string, assignee_id: string}>  $affectedTasks
     */
    public function __construct(
        public array $memberships,
        public array $affectedTasks,
    ) {}
}
