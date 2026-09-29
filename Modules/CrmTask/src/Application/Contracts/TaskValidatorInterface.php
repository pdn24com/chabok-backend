<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Contracts;

use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\CrmTask\Application\Dto\TaskAssignmentDto;
use Modules\CrmTask\Application\Dto\TaskDraftDto;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

interface TaskValidatorInterface
{
    public function validateDraft(string $hqId, TaskDraftDto $draft): void;

    public function validateAssignment(string $hqId, TaskRecord $current, TaskAssignmentDto $assignment, string $actorId): void;

    public function validateAction(string $hqId, TaskRecord $current, TaskActionDto $action): void;
}
