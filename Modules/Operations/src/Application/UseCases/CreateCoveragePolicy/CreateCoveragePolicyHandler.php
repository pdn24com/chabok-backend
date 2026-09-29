<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoveragePolicy;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\CoverageChangeRecorderInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand;
use Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;

final readonly class CreateCoveragePolicyHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CoverageChangeRecorderInterface $coverageChangeRecorder,
        private GetCoveragePolicyHandler $getCoveragePolicyHandler,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(CreateCoveragePolicyCommand $command): CoveragePolicyRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.manage_draft');
        $id = $this->connection->transaction(function () use ($actor, $hq, $input, $correlationId): string {
            if ($this->coverageRepository->policyCodeTaken($hq, $input->code)) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'operations.coverage_policy_code_already_exists');
            }
            $policy = new CoveragePolicyRecord;
            $policy->forceFill([

                'hq_id' => $hq,
                'policy_code' => $input->code,
                'policy_title' => $input->title,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ])->save();
            $id = (string) $policy->getKey();
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_POLICY_CREATED', 'COVERAGE_POLICY', $id, ConfigVersionStatus::Draft->value, $correlationId);

            return $id;
        }, attempts: 3);

        return $this->getCoveragePolicyHandler->handle(new GetCoveragePolicyCommand($actor, $id));
    }
}
