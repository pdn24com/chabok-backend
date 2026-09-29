<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\CompleteTask;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CompleteTaskCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $taskId,
        public string $completionResult,
    ) {}
}
