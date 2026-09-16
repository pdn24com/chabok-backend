<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application;

use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardHandler;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardCommand;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationsDashboardQuery
{
    public function __construct(private GetOperationsDashboardHandler $handler)
    {
    }

    public function get(AuthenticatedPrincipal $actor, string $nodeId): array
    {
        return $this->handler->handle(new GetOperationsDashboardCommand($actor, $nodeId))->data;
    }
}
