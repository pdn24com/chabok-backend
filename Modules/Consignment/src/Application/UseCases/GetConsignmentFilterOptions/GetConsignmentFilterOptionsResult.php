<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignmentFilterOptions;

final readonly class GetConsignmentFilterOptionsResult
{
    public function __construct(public array $data)
    {
    }
}
