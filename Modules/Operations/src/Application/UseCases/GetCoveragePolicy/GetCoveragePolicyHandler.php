<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetCoveragePolicy;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\NetworkAccessGuardInterface;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\CoveragePolicyRecord;

final readonly class GetCoveragePolicyHandler
{
    public function __construct(
        private NetworkAccessGuardInterface $networkAccessGuard,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    public function handle(GetCoveragePolicyCommand $command): CoveragePolicyRecord
    {
        $actor = $command->actor;
        $id = $command->id;
        $hq = $this->networkAccessGuard->assert($actor, 'network.coverage.view');
        $row = $this->coverageRepository->findPolicy($hq, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
