<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Dto\RouteVersionDto;

final readonly class CreateRouteVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $definitionId,
        public RouteVersionDto $input,
        public string $correlationId,
    ) {}
}
