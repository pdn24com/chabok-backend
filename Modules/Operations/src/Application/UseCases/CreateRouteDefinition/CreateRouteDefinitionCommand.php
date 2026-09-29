<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteDefinition;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\RouteDefinitionDto;

final readonly class CreateRouteDefinitionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public RouteDefinitionDto $input,
        public string $correlationId,
    ) {}
}
