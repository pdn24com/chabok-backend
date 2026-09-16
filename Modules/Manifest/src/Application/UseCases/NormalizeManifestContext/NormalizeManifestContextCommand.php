<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\NormalizeManifestContext;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class NormalizeManifestContextCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public array $input)
    {
    }
}
