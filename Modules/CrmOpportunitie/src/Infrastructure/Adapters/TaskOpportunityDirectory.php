<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Infrastructure\Adapters;

use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmTask\Application\Ports\OpportunityDirectoryInterface;

/**
 * The opportunity a task hangs off. The database refuses a task whose customer disagrees with its
 * opportunity, so CrmTask proves the pair through this port before attempting the write.
 */
final readonly class TaskOpportunityDirectory implements OpportunityDirectoryInterface
{
    public function __construct(private OpportunityRepositoryInterface $opportunityRepository) {}

    public function existsForCustomer(string $hqId, string $customerId, string $opportunityId): bool
    {
        return $this->opportunityRepository->existsForCustomer($hqId, $customerId, $opportunityId);
    }

    public function titlesFor(string $hqId, array $opportunityIds): array
    {
        return $this->opportunityRepository->titlesFor($hqId, $opportunityIds);
    }
}
