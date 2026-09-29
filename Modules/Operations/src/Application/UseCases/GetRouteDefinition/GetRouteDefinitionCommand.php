<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteDefinition;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetRouteDefinitionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $id) {}
}
