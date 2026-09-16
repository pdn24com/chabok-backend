<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoverageVersions;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ListCoverageVersionsHandler
{
    public function __construct(
        private \Modules\Operations\Application\NetworkAccessGuard $access,
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
    )
    {
    }

    public function handle(ListCoverageVersionsCommand $command): ListCoverageVersionsResult
    {
        return new ListCoverageVersionsResult($this->execute($command->actor, $command->policyId, $command->page, $command->perPage));
    }

    private function execute(AuthenticatedPrincipal $actor, string $policyId, int $page = 1, int $perPage = 20): Page
    {
        $hq = $this->access->assert($actor, 'network.coverage.view');
        if (!$this->coverage->policyExists($hq, $policyId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $this->coverage->history($hq, $policyId, $page, $perPage);
    }
}
