<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListAreas;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Organization\Application\Dto\NetworkFiltersDto;

final readonly class ListAreasCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public NetworkFiltersDto $filters) {}
}
