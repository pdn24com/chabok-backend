<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateCoverageVersion;

final readonly class UpdateCoverageVersionResult
{
    public function __construct(public array $data)
    {
    }
}
