<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public string $id)
    {
    }
}
