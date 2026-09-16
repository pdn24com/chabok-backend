<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ValidateNumberRange;

final readonly class ValidateNumberRangeResult
{
    public function __construct(public array $data)
    {
    }
}
