<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetNumberRangeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $rangeId)
    {
    }
}
