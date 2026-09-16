<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

final readonly class GetOperationsDashboardCommand
{
    public function __construct(public \Modules\Foundation\Domain\AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
