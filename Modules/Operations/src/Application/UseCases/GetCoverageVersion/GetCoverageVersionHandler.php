<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoverageVersion;

use Modules\Operations\Application\Contracts\CoveragePolicyReaderInterface;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyVersionRecord;

final readonly class GetCoverageVersionHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private CoveragePolicyReaderInterface $coveragePolicyReader,
    ) {}

    public function handle(GetCoverageVersionCommand $command): CoveragePolicyVersionRecord
    {
        $actor = $command->actor;
        $policyId = $command->policyId;
        $versionId = $command->versionId;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.view');
        $row = $this->coveragePolicyReader->versionRow($hq, $policyId, $versionId);

        return $row;
    }
}
