<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoverageVersion;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class GetCoverageVersionHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
    )
    {
    }

    public function handle(GetCoverageVersionCommand $command): GetCoverageVersionResult
    {
        return new GetCoverageVersionResult($this->execute($command->actor, $command->policyId, $command->versionId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $policyId, string $versionId): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        $row = $this->coveragePolicyReader->versionRow($hq, $policyId, $versionId);
        return $this->coveragePolicyReader->versionArray($row);
    }
}
