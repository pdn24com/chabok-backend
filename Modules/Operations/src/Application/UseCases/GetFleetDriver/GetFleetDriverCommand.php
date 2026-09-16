<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetDriver;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetFleetDriverCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $driverId)
    {
    }
}
