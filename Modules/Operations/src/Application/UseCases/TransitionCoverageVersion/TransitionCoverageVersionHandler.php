<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class TransitionCoverageVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
        private \Modules\Operations\Application\Services\CoverageVersionGuard $coverageVersionGuard,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Application\Services\CoverageChangeRecorder $coverageChangeRecorder,
        private \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionHandler $getCoverageVersion,
    )
    {
    }

    public function handle(TransitionCoverageVersionCommand $command): TransitionCoverageVersionResult
    {
        return new TransitionCoverageVersionResult($this->execute($command->actor, $command->policyId, $command->versionId, $command->action, $command->expected, $command->note, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $policyId,
        string $versionId,
        string $action,
        int $expected,
        ?string $note,
        string $correlationId,
    ): array
    {
        $permission = match ($action) {
            'validate' => 'network.coverage.validate',
            'approve' => 'network.coverage.approve',
            'publish', 'supersede' => 'network.coverage.publish',
            default => 'network.coverage.manage_draft',
        };
        $hq = $this->access->assert($actor, $permission);
        $this->transactions->run(function () use ($actor, $hq, $policyId, $versionId, $action, $expected, $note, $correlationId): void {
            $row = $this->coveragePolicyReader->lockedVersion($hq, $policyId, $versionId);
            $this->coverageVersionGuard->expected($row, $expected);
            $next = match ($action) {
                'validate' => $this->coverageVersionGuard->validatedChanges($hq, $row, $actor->userId),
                'approve' => $this->coverageVersionGuard->simpleChanges($row, 'VALIDATED', 'APPROVED', ['approved_by' => $actor->userId, 'approved_at' => $this->clock->now()]),
                'publish' => $this->coverageVersionGuard->publishedChanges($hq, $row, $actor->userId),
                'supersede' => $this->coverageVersionGuard->simpleChanges($row, 'PUBLISHED', 'SUPERSEDED'),
                'archive' => $this->coverageVersionGuard->archiveChanges($row),
                default => throw new ApiException(ApiErrorCode::ValidationError, 422, 'Unknown lifecycle action.'),
            };
            $next['version'] = (int) $row->version + 1;
            $next['updated_at'] = $this->clock->now();
            $this->coverage->updateVersion($versionId, $next);
            if ($action === 'publish') {
                $this->coverage->publishPolicy($policyId, $versionId, $this->clock->now());
            }
            if ($action === 'supersede') {
                $this->coverage->supersedePolicy($policyId, $versionId, $this->clock->now());
            }
            $this->coverageChangeRecorder->record($actor, 'COVERAGE_VERSION_' . strtoupper($action), 'COVERAGE_POLICY_VERSION', $versionId, (string) $next['status'], $correlationId, $note);
        });
        return $this->getCoverageVersion->handle(new \Modules\Operations\Application\UseCases\GetCoverageVersion\GetCoverageVersionCommand($actor, $policyId, $versionId))->data;
    }
}
