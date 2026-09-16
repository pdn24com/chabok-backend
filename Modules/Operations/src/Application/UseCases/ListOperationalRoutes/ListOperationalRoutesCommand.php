<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListOperationalRoutes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListOperationalRoutesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
