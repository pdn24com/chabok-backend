<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListNodes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListNodesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
