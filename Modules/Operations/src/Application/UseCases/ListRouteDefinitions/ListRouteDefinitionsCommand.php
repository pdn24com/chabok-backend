<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRouteDefinitions;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\RouteDefinitionFiltersDto;

final readonly class ListRouteDefinitionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public RouteDefinitionFiltersDto $filters) {}
}
