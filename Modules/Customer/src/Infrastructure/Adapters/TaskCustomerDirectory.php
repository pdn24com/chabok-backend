<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Adapters;

use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;

/**
 * The customer behind a task. Customer already reads CrmTask for the open work on its detail page, so
 * it fills the CrmTask port here rather than being read back the other way.
 */
final readonly class TaskCustomerDirectory implements CustomerDirectoryInterface
{
    public function __construct(private CustomerRepositoryInterface $customerRepository) {}

    public function existsForTenant(string $hqId, string $customerId): bool
    {
        return $this->customerRepository->existsForTenant($hqId, $customerId);
    }

    public function displayNamesFor(string $hqId, array $customerIds): array
    {
        return $this->customerRepository->displayNamesFor($hqId, $customerIds);
    }
}
