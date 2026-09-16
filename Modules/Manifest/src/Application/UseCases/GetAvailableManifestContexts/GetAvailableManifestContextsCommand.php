<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetAvailableManifestContexts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetAvailableManifestContextsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
