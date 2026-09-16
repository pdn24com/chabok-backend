<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestContextOptions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestContextOptionsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
