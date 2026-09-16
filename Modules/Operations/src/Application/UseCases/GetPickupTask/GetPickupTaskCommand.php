<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetPickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetPickupTaskCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public string $id)
    {
    }
}
