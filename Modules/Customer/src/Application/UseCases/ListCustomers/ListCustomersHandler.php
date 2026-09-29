<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomers;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;

final readonly class ListCustomersHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function handle(ListCustomersCommand $command): ListCustomersResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListCustomersResult($this->customerRepository->paginateForTenant($hqId, $command->filters));
    }
}
