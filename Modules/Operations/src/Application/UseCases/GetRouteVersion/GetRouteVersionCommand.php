<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetRouteVersionCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $definitionId, public string $versionId)
    {
    }
}
