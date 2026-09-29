<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

use Carbon\CarbonImmutable;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final readonly class ResolveCoveragePolicyResult
{
    public function __construct(
        public CoverageRuleRecord $rule,
        public CoverageCriterion $criterion,
        public CoverageLocation $location,
        public CarbonImmutable $resolvedAt,
    ) {}
}
