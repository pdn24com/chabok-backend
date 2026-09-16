<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionCoverageVersion;

final readonly class TransitionCoverageVersionResult
{
    public function __construct(public array $data)
    {
    }
}
