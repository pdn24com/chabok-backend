<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateNumberRange;

final readonly class CreateNumberRangeResult
{
    public function __construct(public array $data)
    {
    }
}
