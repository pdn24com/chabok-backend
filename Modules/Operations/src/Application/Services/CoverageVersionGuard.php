<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoverageRuleGuardInterface;
use Modules\Operations\Application\Contracts\CoverageVersionGuardInterface;
use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Application\Serialization\CoveragePolicyDocument;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class CoverageVersionGuard implements CoverageVersionGuardInterface
{
    public function __construct(
        private CoverageRuleGuardInterface $coverageRuleGuard,
        private ClockInterface $clock,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function expected(CoveragePolicyVersionRecord $row, int $expected): void
    {
        if ((int) $row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.coverage_version_is_stale', details: ['current_version' => (int) $row->version]);
        }
    }

    public function validatedChanges(
        string $hq,
        CoveragePolicyVersionRecord $row,
        string $user,
    ): array {
        if ($row->status !== ConfigVersionStatus::Draft->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_draft_can_be_validated');
        }
        $this->coverageRuleGuard->validateRuleInputs($hq, $row->rules->map(CoverageRuleDto::fromRecord(...))->all());
        $this->assertDates($row);

        return [
            'status' => ConfigVersionStatus::Validated->value,
            'validated_by' => $user,
            'validated_at' => $this->clock->now(),
        ];
    }

    public function simpleChanges(
        CoveragePolicyVersionRecord $row,
        string $from,
        string $to,
        array $extra = [],
    ): array {
        if ($row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_can_transition', messageParams: ['from' => $from, 'to' => $to]);
        }

        return ['status' => $to, ...$extra];
    }

    public function archiveChanges(CoveragePolicyVersionRecord $row): array
    {
        if (! in_array($row->status, [ConfigVersionStatus::Draft->value, ConfigVersionStatus::Validated->value, ConfigVersionStatus::Approved->value], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.published_superseded_versions_cannot_be_archived');
        }

        return ['status' => ConfigVersionStatus::Archived->value];
    }

    public function publishedChanges(
        string $hq,
        CoveragePolicyVersionRecord $row,
        string $user,
    ): array {
        if ($row->status !== ConfigVersionStatus::Approved->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_approved_version_can_be_published');
        }
        $this->assertDates($row);
        $overlap = $this->coverageRepository->hasOverlappingPublishedVersion($hq, $row, $this->clock->now());
        if ($overlap) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.published_coverage_version_effective_dates_cannot_overlap');
        }
        $detail = CoveragePolicyDocument::version($row);

        return [
            'status' => ConfigVersionStatus::Published->value,
            'published_by' => $user,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode($detail, JSON_THROW_ON_ERROR)),
        ];
    }

    public function assertDates(CoveragePolicyVersionRecord $row): void
    {
        if ($row->effective_from !== null && $row->effective_to !== null && CarbonImmutable::parse($row->effective_from)->gte(CarbonImmutable::parse($row->effective_to))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.effective_must_be_after_effective_from');
        }
    }
}
