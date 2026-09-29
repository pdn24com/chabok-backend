<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoverageVersion;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;
use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Contracts\CoverageRuleGuardInterface;
use Modules\Operations\Application\Contracts\CoverageRuleWriterInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand;
use Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class CreateCoverageVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private CoveragePolicyReaderInterface $coveragePolicyReader,
        private CoverageRuleGuardInterface $coverageRuleGuard,
        private ClockInterface $clock,
        private CoverageRuleWriterInterface $coverageRuleWriter,
        private CoverageChangeRecorderInterface $coverageChangeRecorder,
        private GetCoverageVersionHandler $getCoverageVersionHandler,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(CreateCoverageVersionCommand $command): CoveragePolicyVersionRecord
    {
        $actor = $command->actor;
        $policyId = $command->policyId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.manage_draft');
        $id = $this->connection->transaction(function () use ($actor, $hq, $policyId, $input, $correlationId): string {
            if (! $this->coverageRepository->policyExists($hq, $policyId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $rules = $input->rules;
            if ($input->sourceVersionId !== null && $rules === []) {
                $rules = $this->coveragePolicyReader->ruleInputs($hq, $input->sourceVersionId);
            }
            $this->coverageRuleGuard->validateRuleInputs($hq, $rules);
            $number = $this->coverageRepository->nextVersionNumber($policyId);
            $version = new CoveragePolicyVersionRecord;
            $version->forceFill([

                'hq_id' => $hq,
                'coverage_policy_id' => $policyId,
                'version_number' => $number,
                'status' => ConfigVersionStatus::Draft->value,
                'effective_from' => $input->effectiveFrom,
                'effective_to' => $input->effectiveTo,
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $id = (string) $version->getKey();
            $this->coverageRuleWriter->replaceRules($hq, $id, $rules);
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_CREATED', 'COVERAGE_POLICY_VERSION', $id, ConfigVersionStatus::Draft->value, $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getCoverageVersionHandler->handle(new GetCoverageVersionCommand($actor, $policyId, $id));
    }
}
