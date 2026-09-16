<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoveragePolicy;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetCoveragePolicyHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Application\Services\CoveragePolicyReader $coveragePolicyReader,
    )
    {
    }

    public function handle(GetCoveragePolicyCommand $command): GetCoveragePolicyResult
    {
        return new GetCoveragePolicyResult($this->execute($command->actor, $command->id));
    }

    private function execute(AuthenticatedPrincipal $actor, string $id): array
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        $row = $this->coverage->policy($hq, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->coveragePolicyReader->policyArray($row);
    }
}
