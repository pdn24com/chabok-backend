<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoveragePolicies;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;

final readonly class ListCoveragePoliciesHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(ListCoveragePoliciesCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.view');

        return $this->coverageRepository->paginatePolicies($hq, $filters);
    }
}
