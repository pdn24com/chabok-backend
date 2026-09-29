<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberRanges;

use Modules\Consignment\Application\Dto\NumberRangeFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListNumberRangesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public NumberRangeFiltersDto $filters) {}
}
