<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListDeliveryTasks;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListDeliveryTasksCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public array $filters = [])
    {
    }
}
