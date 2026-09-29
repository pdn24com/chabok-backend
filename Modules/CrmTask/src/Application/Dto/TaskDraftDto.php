<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

use DateTimeImmutable;
use Modules\CrmTask\Domain\Enums\TaskPriority;

final readonly class TaskDraftDto
{
    public function __construct(
        public string $title,
        public TaskPriority $priority,
        public ?string $description = null,
        public ?DateTimeImmutable $dueAt = null,
        public ?DateTimeImmutable $remindAt = null,
        public ?string $customerId = null,
        public ?string $opportunityId = null,
        /** null leaves the task without an owner; it never means a team queue. */
        public ?string $assigneeId = null,
        /** Historical context only: it lands on the assignment event, never on the task itself. */
        public ?string $teamContextId = null,
        public ?string $operationKey = null,
    ) {}
}
