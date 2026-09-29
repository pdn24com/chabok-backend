<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

use DateTimeImmutable;
use Modules\CrmTask\Domain\Enums\TaskAssignmentEventType;

/** One move of a task between owners, and the deadline it carries over with it. */
final readonly class TaskAssignmentDto
{
    public function __construct(
        public TaskAssignmentEventType $eventType,
        public ?string $toTeamId = null,
        /** null leaves the task without an owner; it never means a team queue. */
        public ?string $toUserId = null,
        public ?string $reason = null,
        public ?DateTimeImmutable $dueAt = null,
        public bool $dueSpecified = false,
        public ?DateTimeImmutable $remindAt = null,
        public bool $remindSpecified = false,
        /** The owner the caller believed the task had; a mismatch means somebody moved it first. */
        public ?string $expectedAssigneeId = null,
        public bool $expectedAssigneeSpecified = false,
        public ?string $operationKey = null,
    ) {}
}
