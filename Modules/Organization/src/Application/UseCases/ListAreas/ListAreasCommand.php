<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListAreas;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListAreasCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
