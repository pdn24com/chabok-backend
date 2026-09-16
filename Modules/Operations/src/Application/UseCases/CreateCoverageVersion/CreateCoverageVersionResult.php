<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoverageVersion;

final readonly class CreateCoverageVersionResult
{
    public function __construct(public array $data)
    {
    }
}
