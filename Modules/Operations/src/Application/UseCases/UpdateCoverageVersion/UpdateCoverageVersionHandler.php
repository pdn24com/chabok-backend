<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateCoverageVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;
use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Contracts\CoverageRuleGuardInterface;
use Modules\Operations\Application\Contracts\CoverageRuleWriterInterface;
use Modules\Operations\Application\Contracts\CoverageVersionGuardInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class UpdateCoverageVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private CoveragePolicyReaderInterface $coveragePolicyReader,
        private CoverageVersionGuardInterface $coverageVersionGuard,
        private ClockInterface $clock,
        private CoverageRuleGuardInterface $coverageRuleGuard,
        private CoverageRuleWriterInterface $coverageRuleWriter,
        private CoverageChangeRecorderInterface $coverageChangeRecorder,
        private GetCoverageVersionHandler $getCoverageVersionHandler,
    ) {}

    public function handle(UpdateCoverageVersionCommand $command): CoveragePolicyVersionRecord
    {
        $actor = $command->actor;
        $policyId = $command->policyId;
        $versionId = $command->versionId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.manage_draft');
        $this->connection->transaction(function () use ($actor, $hq, $policyId, $versionId, $input, $correlationId): void {
            $row = $this->coveragePolicyReader->lockedVersion($hq, $policyId, $versionId);
            if ($row->status !== ConfigVersionStatus::Draft->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_draft_version_is_editable');
            }
            $this->coverageVersionGuard->expected($row, $input->expectedVersion);
            $changes = ['version' => (int) $row->version + 1, 'updated_at' => $this->clock->now()];
            if ($input->effectiveFromProvided) {
                $changes['effective_from'] = $input->effectiveFrom;
            }
            if ($input->effectiveToProvided) {
                $changes['effective_to'] = $input->effectiveTo;
            }
            if ($input->rules !== null) {
                $this->coverageRuleGuard->validateRuleInputs($hq, $input->rules);
                $this->coverageRuleWriter->replaceRules($hq, $versionId, $input->rules);
            }
            $row->forceFill($changes)->save();
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_UPDATED', 'COVERAGE_POLICY_VERSION', $versionId, ConfigVersionStatus::Draft->value, $correlationId);
        }, attempts: 3);

        return $this->getCoverageVersionHandler->handle(new GetCoverageVersionCommand($actor, $policyId, $versionId));
    }
}
