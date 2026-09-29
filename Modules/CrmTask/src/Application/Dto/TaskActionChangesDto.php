<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

use DateTimeImmutable;
use Modules\CrmTask\Domain\Enums\TaskStatus;

/**
 * What recording an action does to the task beside it. Every field is optional: an action may simply be
 * logged. The deadline and the reminder each carry a flag, so clearing one is told from leaving it.
 */
final readonly class TaskActionChangesDto
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?DateTimeImmutable $dueAt = null,
        public bool $dueSpecified = false,
        public ?DateTimeImmutable $remindAt = null,
        public bool $remindSpecified = false,
        public ?string $completionResult = null,
    ) {}
}
