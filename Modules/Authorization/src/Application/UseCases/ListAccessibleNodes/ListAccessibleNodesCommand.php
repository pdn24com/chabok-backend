<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ListAccessibleNodes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAccessibleNodesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor)
    {
    }
}
