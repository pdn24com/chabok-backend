<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CountConsignmentStatusGroups;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CountConsignmentStatusGroupsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $nodeId, public array $filters)
    {
    }
}
