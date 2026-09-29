<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ValidateNumberRangeCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public NumberRangeInput $input) {}
}
