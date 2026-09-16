<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\DisableNumberRange;

final readonly class DisableNumberRangeResult
{
    public function __construct(public array $data)
    {
    }
}
