<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\RecordTaskAction;

use Modules\CrmTask\Application\Dto\TaskActionDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class RecordTaskActionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $taskId,
        public TaskActionDto $action,
    ) {}
}
