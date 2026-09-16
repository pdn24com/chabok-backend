<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

final readonly class ResolveCoveragePolicyResult
{
    public function __construct(public array $data)
    {
    }
}
