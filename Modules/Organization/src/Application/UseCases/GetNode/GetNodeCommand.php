<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetNode;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetNodeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId)
    {
    }
}
