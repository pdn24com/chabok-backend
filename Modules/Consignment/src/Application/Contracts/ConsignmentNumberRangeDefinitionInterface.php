<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;

interface ConsignmentNumberRangeDefinitionInterface
{
    public function validate(NumberRangeInput $input): NumberRangePreview;
}
