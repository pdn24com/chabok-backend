<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateCoverageVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
        private \Modules\Operations\Application\Services\CoverageRuleGuard $coverageRuleGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\CoverageRuleWriter $coverageRuleWriter,
        private \Modules\Operations\Application\Services\CoverageChangeRecorder $coverageChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler $getCoverageVersion,
    )
    {
    }

    public function handle(CreateCoverageVersionCommand $command): CreateCoverageVersionResult
    {
        return new CreateCoverageVersionResult($this->execute($command->actor, $command->policyId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $policyId, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $policyId, $input, $correlationId): string {
            if (!$this->coverage->policyExists($hq, $policyId)) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $rules = (array) ($input['rules'] ?? []);
            if (($input['source_version_id'] ?? null) !== null && $rules === []) {
                $rules = $this->coveragePolicyReader->ruleInputs($hq, (string) $input['source_version_id']);
            }
            $this->coverageRuleGuard->validateRuleInputs($hq, $rules);
            $id = $this->identifiers->uuid();
            $number = (int) $this->coverage->lastVersionNumber($policyId) + 1;
            $this->coverage->insertVersion([
                'coverage_policy_version_id' => $id,
                'hq_id' => $hq,
                'coverage_policy_id' => $policyId,
                'version_number' => $number,
                'status' => 'DRAFT',
                'effective_from' => $input['effective_from'] ?? null,
                'effective_to' => $input['effective_to'] ?? null,
                'version' => 1,
                'created_by' => $actor->userId,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->coverageRuleWriter->replaceRules($hq, $id, $rules);
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_CREATED', 'COVERAGE_POLICY_VERSION', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->getCoverageVersion->handle(new \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand($actor, $policyId, $id))->data;
    }
}
