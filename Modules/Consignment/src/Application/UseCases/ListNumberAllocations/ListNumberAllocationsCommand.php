<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberAllocations;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListNumberAllocationsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $rangeId, public array $filters)
    {
    }
}
