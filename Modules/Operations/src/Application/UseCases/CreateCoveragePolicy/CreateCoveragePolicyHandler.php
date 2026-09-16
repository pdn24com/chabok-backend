<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateCoveragePolicy;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class CreateCoveragePolicyHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Services\CoverageChangeRecorder $coverageChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyHandler $getCoveragePolicy,
    )
    {
    }

    public function handle(CreateCoveragePolicyCommand $command): CreateCoveragePolicyResult
    {
        return new CreateCoveragePolicyResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.manage_draft');
        $id = $this->transactions->run(function () use ($actor, $hq, $input, $correlationId): string {
            if ($this->coverage->codeExists($hq, $input['policy_code'])) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'The Coverage Policy code already exists.');
            }
            $id = $this->identifiers->uuid();
            $this->coverage->insertPolicy([
                'coverage_policy_id' => $id,
                'hq_id' => $hq,
                'policy_code' => $input['policy_code'],
                'policy_title' => $input['policy_title'],
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_POLICY_CREATED', 'COVERAGE_POLICY', $id, 'DRAFT', $correlationId);
            return $id;
        });
        return $this->getCoveragePolicy->handle(new \Modules\Operations\Application\UseCases\GetCoveragePolicy\GetCoveragePolicyCommand($actor, $id))->data;
    }
}
