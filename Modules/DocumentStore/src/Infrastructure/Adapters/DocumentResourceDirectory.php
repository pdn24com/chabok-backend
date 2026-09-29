<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Infrastructure\Adapters;

use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\DocumentStore\Application\Ports\DocumentResourceDirectoryInterface;
use Modules\DocumentStore\Domain\Enums\DocumentResourceType;

/**
 * Resolves one entry of the link registry against the module that owns that kind of record. This is the
 * only place that knows the mapping, so adding a kind is an arm here beside a case on the enum.
 */
final readonly class DocumentResourceDirectory implements DocumentResourceDirectoryInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
    ) {}

    public function exists(string $hqId, DocumentResourceType $resourceType, string $resourceId): bool
    {
        return match ($resourceType) {
            DocumentResourceType::CUSTOMER => $this->customerRepository->existsForTenant($hqId, $resourceId),
            DocumentResourceType::OPPORTUNITY => $this->opportunityRepository->existsForTenant($hqId, $resourceId),
        };
    }
}
