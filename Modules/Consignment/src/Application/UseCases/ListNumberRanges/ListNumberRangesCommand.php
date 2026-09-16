<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListNumberRanges;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListNumberRangesCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters)
    {
    }
}
