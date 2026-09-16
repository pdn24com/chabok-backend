<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRoutePlans;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListRoutePlansCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
