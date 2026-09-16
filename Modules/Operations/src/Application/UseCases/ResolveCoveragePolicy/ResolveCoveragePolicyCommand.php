<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

use DateTimeInterface;

final readonly class ResolveCoveragePolicyCommand
{
    public function __construct(
        public string $hqId,
        public string $target,
        public array $input,
        public ?string $offeringVersionId = null,
        public ?DateTimeInterface $at = null,
    )
    {
    }
}
