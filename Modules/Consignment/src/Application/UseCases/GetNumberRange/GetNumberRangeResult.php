<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetNumberRange;

final readonly class GetNumberRangeResult
{
    public function __construct(public array $data)
    {
    }
}
