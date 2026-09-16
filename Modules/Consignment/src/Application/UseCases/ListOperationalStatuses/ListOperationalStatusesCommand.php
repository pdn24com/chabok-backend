<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatuses;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListOperationalStatusesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor)
    {
    }
}
