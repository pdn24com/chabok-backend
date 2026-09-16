<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListFleetDrivers;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListFleetDriversCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
