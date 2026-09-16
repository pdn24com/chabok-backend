<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifests;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListManifestsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public array $filters)
    {
    }
}
