<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateNumberRangeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $input)
    {
    }
}
