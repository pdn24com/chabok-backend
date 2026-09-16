<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteDefinition;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateRouteDefinitionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input, public string $correlationId)
    {
    }
}
