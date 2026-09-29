<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCommitmentScheduleHistory;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;

final readonly class GetCommitmentScheduleHistoryHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(GetCommitmentScheduleHistoryCommand $command): array
    {
        $actor = $command->actor;
        $identityId = $command->identityId;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.history.view');
        if (! $this->commitmentScheduleRepository->identityExists($actor->hqId, $identityId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        $versions = $this->commitmentScheduleRepository->versionHistoryOf((string) $actor->hqId, $identityId);

        return $versions->map(ScheduleDocument::version(...))->all();
    }
}
