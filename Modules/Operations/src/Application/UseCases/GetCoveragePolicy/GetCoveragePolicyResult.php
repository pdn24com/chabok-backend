<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoveragePolicy;

final readonly class GetCoveragePolicyResult
{
    public function __construct(public array $data)
    {
    }
}
