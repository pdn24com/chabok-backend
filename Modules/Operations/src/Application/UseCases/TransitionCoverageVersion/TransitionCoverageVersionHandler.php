<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionCoverageVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;
use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Contracts\CoverageVersionGuardInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class TransitionCoverageVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private CoveragePolicyReaderInterface $coveragePolicyReader,
        private CoverageVersionGuardInterface $coverageVersionGuard,
        private ClockInterface $clock,
        private CoverageChangeRecorderInterface $coverageChangeRecorder,
        private GetCoverageVersionHandler $getCoverageVersionHandler,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(TransitionCoverageVersionCommand $command): CoveragePolicyVersionRecord
    {
        $actor = $command->actor;
        $policyId = $command->policyId;
        $versionId = $command->versionId;
        $action = $command->action;
        $expected = $command->expected;
        $note = $command->note;
        $correlationId = $command->correlationId;
        $permission = match ($action) {
            'validate' => 'network.coverage.validate',
            'approve' => 'network.coverage.approve',
            'publish', 'supersede' => 'network.coverage.publish',
            default => 'network.coverage.manage_draft',
        };
        $hq = $this->networkAccessGuard->assert($actor, $permission);
        $this->connection->transaction(function () use ($actor, $hq, $policyId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->coveragePolicyReader->lockedVersion($hq, $policyId, $versionId);
            $this->coverageVersionGuard->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->coverageVersionGuard->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->coverageVersionGuard->simpleChanges($row, ConfigVersionStatus::Validated->value, ConfigVersionStatus::Approved->value, ['approved_by' => $actor->userId, 'approved_at' => $this->clock->now()]),
                'publish' => $this->coverageVersionGuard->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->coverageVersionGuard->simpleChanges($row, ConfigVersionStatus::Published->value, ConfigVersionStatus::Superseded->value),
                'archive' => $this->coverageVersionGuard->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.unknown_lifecycle_action'),
            };
            $next['version'] = (int) $row->version + 1;
            $next['updated_at'] = $this->clock->now();
            $row->forceFill($next)->save();
            if ($action === 'publish') {
                $this->coverageRepository->updatePolicy($policyId, ['published_version_id' => $versionId, 'updated_at' => $this->clock->now()]);
            }
            if ($action === 'supersede') {
                $this->coverageRepository->clearPublishedVersion($policyId, $versionId, ['published_version_id' => null, 'updated_at' => $this->clock->now()]);
            }
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_'.strtoupper($action), 'COVERAGE_POLICY_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        }, attempts: 3);

        return $this->getCoverageVersionHandler->handle(new GetCoverageVersionCommand($actor, $policyId, $versionId));
    }
}
