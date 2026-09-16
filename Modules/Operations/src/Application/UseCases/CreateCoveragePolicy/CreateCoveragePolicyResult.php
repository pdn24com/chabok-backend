<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoveragePolicy;

final readonly class CreateCoveragePolicyResult
{
    public function __construct(public array $data)
    {
    }
}
