<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListCoverageVersions;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;

final readonly class ListCoverageVersionsHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(ListCoverageVersionsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $policyId = $command->policyId;
        $page = $command->page;
        $perPage = $command->perPage;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.view');
        if (! $this->coverageRepository->policyExists($hq, $policyId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $this->coverageRepository->paginateVersions($hq, $policyId, $page, $perPage);
    }
}
