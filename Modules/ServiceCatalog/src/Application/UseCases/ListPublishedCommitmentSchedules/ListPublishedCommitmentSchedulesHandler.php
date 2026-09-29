<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListPublishedCommitmentSchedules;

use Modules\ServiceCatalog\Application\Contracts\CatalogAccessGuardInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Application\Serialization\ScheduleDocument;

final readonly class ListPublishedCommitmentSchedulesHandler
{
    public function __construct(
        private CatalogAccessGuardInterface $catalogAccessGuard,
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function handle(ListPublishedCommitmentSchedulesCommand $command): array
    {
        $actor = $command->actor;
        $includeVersionIds = $command->includeVersionIds;
        $this->catalogAccessGuard->assertAccess($actor, 'service_catalog.view');
        $includeVersionIds = array_values(array_unique(array_map('strval', $includeVersionIds)));
        $identities = $this->commitmentScheduleRepository->identitiesWithLatestVersion($actor->hqId, $includeVersionIds);
        $includeVersionIds = array_map(fn ($id) => $identities->get($id)?->latestVersion?->commitment_schedule_version_id ?? $id, $includeVersionIds);
        $versions = $this->commitmentScheduleRepository->availableVersions((string) $actor->hqId, $includeVersionIds);

        return $versions->map(ScheduleDocument::version(...))->all();
    }
}
