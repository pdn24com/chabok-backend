<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

use DateTimeInterface;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;

final readonly class ResolveCoveragePolicyCommand
{
    public function __construct(
        public string $hqId,
        public CoverageTarget $target,
        public CoverageLocation $location,
        public ?string $offeringVersionId = null,
        public ?DateTimeInterface $at = null,
    ) {}
}
