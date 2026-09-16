<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class UpdateCoverageVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
        private \Modules\Operations\Application\Services\CoverageVersionGuard $coverageVersionGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\CoverageRuleGuard $coverageRuleGuard,
        private \Modules\Operations\Application\Services\CoverageRuleWriter $coverageRuleWriter,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Application\Services\CoverageChangeRecorder $coverageChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler $getCoverageVersion,
    )
    {
    }

    public function handle(UpdateCoverageVersionCommand $command): UpdateCoverageVersionResult
    {
        return new UpdateCoverageVersionResult($this->execute($command->actor, $command->policyId, $command->versionId, $command->input, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        array $input,
        string $correlationId,
    ): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $this->transactions->run(function () use ($actor, $hq, $policyId, $versionId, $input, $correlationId): void {
            $row = $this->coveragePolicyReader->lockedVersion($hq, $policyId, $versionId);
            if ($row->status !== 'DRAFT') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft version is editable.');
            }
            $this->coverageVersionGuard->expected($row, (int) $input['expected_version']);
            $changes = ['version' => (int) $row->version + 1, 'updated_at' => $this->clock->now()];
            foreach (['effective_from', 'effective_to'] as $field) {
                if (array_key_exists($field, $input)) {
                    $changes[$field] = $input[$field];
                }
            }
            if (array_key_exists('rules', $input)) {
                $this->coverageRuleGuard->validateRuleInputs($hq, $input['rules']);
                $this->coverageRuleWriter->replaceRules($hq, $versionId, $input['rules']);
            }
            $this->coverage->updateVersion($versionId, $changes);
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_UPDATED', 'COVERAGE_POLICY_VERSION', $versionId, 'DRAFT', $correlationId);
        });
        return $this->getCoverageVersion->handle(new \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand($actor, $policyId, $versionId))->data;
    }
}
