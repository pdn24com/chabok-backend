<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateRouteVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $definitionId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
