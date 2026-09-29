<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\ListTasks;

use Modules\CrmTask\Application\Dto\TaskListFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListTasksCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public TaskListFiltersDto $filters) {}
}
