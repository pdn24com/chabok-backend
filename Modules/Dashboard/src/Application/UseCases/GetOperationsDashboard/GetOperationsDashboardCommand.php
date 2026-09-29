<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\UseCases\GetOperationsDashboard;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetOperationsDashboardCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId) {}
}
