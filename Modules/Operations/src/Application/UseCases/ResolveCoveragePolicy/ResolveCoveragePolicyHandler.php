<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ResolveCoveragePolicy;

use Carbon\CarbonImmutable as Carbon;
use DateTimeInterface;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ResolveCoveragePolicyHandler
{
    public function __construct(
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Domain\CoverageMatchPolicy $coverageMatchPolicy,
    )
    {
    }

    public function handle(ResolveCoveragePolicyCommand $command): ResolveCoveragePolicyResult
    {
        return new ResolveCoveragePolicyResult($this->execute($command->hqId, $command->target, $command->input, $command->offeringVersionId, $command->at));
    }

    private function execute(
        string $hqId,
        string $target,
        array $input,
        ?string $offeringVersionId = null,
        ?DateTimeInterface $at = null,
    ): array
    {
        $at ??= $this->clock->now();
        $rows = $this->coverage->matchingRules($hqId, $target, $offeringVersionId, $at);
        $matches = array_values(array_filter($rows, fn($row): bool => $this->coverageMatchPolicy->matches($row, $input)));
        if ($matches === []) {
            throw new ApiException(ApiErrorCode::CoverageNotFound, 422, 'No published Coverage Rule matches the request.');
        }
        $ranked = $matches;
        usort($ranked, fn($a, $b): int => strcmp($this->coverageMatchPolicy->rank($b), $this->coverageMatchPolicy->rank($a)));
        $best = $ranked[0];
        $ties = array_values(array_filter($ranked, fn($row): bool => $this->coverageMatchPolicy->rank($row) === $this->coverageMatchPolicy->rank($best)));
        if (count($ties) > 1) {
            throw new ApiException(ApiErrorCode::CoverageAmbiguous, 422, 'More than one published Coverage Rule has the best rank.', details: ['coverage_rule_ids' => array_map(fn($tie) => $tie->coverage_rule_id, $ties)]);
        }
        return [
            'coverage_policy_id' => (string) $best->coverage_policy_id,
            'coverage_policy_version_id' => (string) $best->coverage_policy_version_id,
            'coverage_rule_id' => (string) $best->coverage_rule_id,
            'policy_code' => (string) $best->policy_code,
            'policy_title' => (string) $best->policy_title,
            'version_number' => (int) $best->version_number,
            'target_node_id' => (string) $best->target_node_id,
            'criterion_type' => (string) $best->criterion_type,
            'priority' => (int) $best->priority,
            'input' => $input,
            'matched_evidence' => $this->coverageMatchPolicy->matchedEvidence($best, $input),
            'resolved_at' => Carbon::instance($at)->utc()->toISOString(),
        ];
    }
}
