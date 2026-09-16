<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateRouteVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $definitionId,
        public string $versionId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
