<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CompleteTask;

use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/** The finished task, with the server's completion time and no live reminder left on it. */
final readonly class CompleteTaskResult
{
    public function __construct(public TaskRecord $task) {}
}
