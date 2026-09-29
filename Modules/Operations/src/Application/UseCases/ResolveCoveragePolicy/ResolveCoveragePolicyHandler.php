<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Mappers\CoverageInput;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Domain\Policies\CoverageMatchPolicy;

final readonly class ResolveCoveragePolicyHandler
{
    public function __construct(
        private ClockInterface $clock,
        private CoverageMatchPolicy $coverageMatchPolicy,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(ResolveCoveragePolicyCommand $command): ResolveCoveragePolicyResult
    {
        $at = $command->at ?? $this->clock->now();
        $rules = $this->coverageRepository->effectiveRules($command->hqId, $command->target, $command->offeringVersionId, $at);
        $best = null;
        $ties = [];
        foreach ($rules as $rule) {
            $criterion = CoverageInput::criterion($rule);
            if (! $this->coverageMatchPolicy->matches($criterion, $command->location)) {
                continue;
            }
            $rank = $best === null ? 1 : $this->coverageMatchPolicy->compare($criterion, $best->criterion);
            if ($rank > 0) {
                $best = new ResolveCoveragePolicyResult($rule, $criterion, $command->location, CarbonImmutable::instance($at)->utc());
                $ties = [$rule->coverage_rule_id];
            } elseif ($rank === 0) {
                $ties[] = $rule->coverage_rule_id;
            }
        }
        if ($best === null) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'operations.no_published_coverage_rule_matches_request');
        }
        if (count($ties) > 1) {
            throw new ApiException(ApiErrorCode::CoverageAmbiguous, 422, 'operations.more_than_one_published_coverage_rule_has', details: ['coverage_rule_ids' => $ties]);
        }

        return $best;
    }
}
