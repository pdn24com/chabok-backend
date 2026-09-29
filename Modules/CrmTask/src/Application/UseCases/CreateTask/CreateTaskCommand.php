<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CreateTask;

use Modules\CrmTask\Application\Dto\TaskDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateTaskCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public TaskDraftDto $input) {}
}
