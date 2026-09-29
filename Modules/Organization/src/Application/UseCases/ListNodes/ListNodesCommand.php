<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListNodes;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\NetworkFiltersDto;

final readonly class ListNodesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public NetworkFiltersDto $filters) {}
}
