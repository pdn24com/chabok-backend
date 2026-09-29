<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\AssignTask;

use Modules\CrmTask\Application\Dto\TaskAssignmentDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AssignTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $taskId,
        public TaskAssignmentDto $assignment,
    ) {}
}
