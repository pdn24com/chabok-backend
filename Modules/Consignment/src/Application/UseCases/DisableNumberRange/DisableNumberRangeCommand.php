<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\DisableNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class DisableNumberRangeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $rangeId, public string $correlationId)
    {
    }
}
