<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;

final readonly class ValidateNumberRangeResult
{
    public function __construct(public NumberRangePreview $preview, public bool $overlapsExistingRange) {}
}
