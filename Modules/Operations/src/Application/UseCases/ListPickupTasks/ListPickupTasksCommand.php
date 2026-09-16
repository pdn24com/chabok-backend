<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListPickupTasks;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPickupTasksCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
