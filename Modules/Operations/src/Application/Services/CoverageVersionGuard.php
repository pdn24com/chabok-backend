<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Carbon\CarbonImmutable as Carbon;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CoverageVersionGuard
{
    public function __construct(
        private \Modules\Operations\Application\Services\CoverageRuleGuard $coverageRuleGuard,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
    )
    {
    }

    public function expected(object $row, int $expected): void
    {
        if ((int) $row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Coverage Version is stale.', details: ['current_version' => (int) $row->version]);
        }
    }

    public function validatedChanges(string $hq, object $row, string $user): array
    {
        if ($row->status !== 'DRAFT') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be validated.');
        }
        $this->coverageRuleGuard->validateRuleInputs($hq, $this->coveragePolicyReader->ruleInputs($hq, $row->coverage_policy_version_id));
        $this->assertDates($row);
        return ['status' => 'VALIDATED', 'validated_by' => $user, 'validated_at' => $this->clock->now()];
    }

    public function simpleChanges(object $row, string $from, string $to, array $extra = []): array
    {
        if ($row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}.");
        }
        return ['status' => $to, ...$extra];
    }

    public function archiveChanges(object $row): array
    {
        if (!in_array($row->status, ['DRAFT', 'VALIDATED', 'APPROVED'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published or superseded versions cannot be archived.');
        }
        return ['status' => 'ARCHIVED'];
    }

    public function publishedChanges(string $hq, object $row, string $user): array
    {
        if ($row->status !== 'APPROVED') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
        }
        $this->assertDates($row);
        $overlap = $this->coverage->publishedDatesOverlap($hq, $row->coverage_policy_id, $row->coverage_policy_version_id, $row->effective_from ?? $this->clock->now(), $row->effective_to ?? Carbon::parse('9999-12-31'));
        if ($overlap) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published Coverage Version effective dates cannot overlap.');
        }
        $detail = $this->coveragePolicyReader->versionArray($row);
        return [
            'status' => 'PUBLISHED',
            'published_by' => $user,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR)),
        ];
    }

    public function assertDates(object $row): void
    {
        if ($row->effective_from !== null && $row->effective_to !== null && Carbon::parse($row->effective_from)->gte(Carbon::parse($row->effective_to))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'effective_to must be after effective_from.');
        }
    }
}
