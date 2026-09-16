<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteDefinitions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListRouteDefinitionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
