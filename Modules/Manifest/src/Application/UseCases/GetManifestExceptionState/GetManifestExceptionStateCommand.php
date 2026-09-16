<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestExceptionState;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetManifestExceptionStateCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $node, public string $id)
    {
    }
}
