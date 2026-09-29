<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerRelationships;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipAssemblerInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListCustomerRelationshipsHandler
{
    public function __construct(
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private RelationshipRepositoryInterface $relationshipRepository,
        private CustomerRelationshipAssemblerInterface $customerRelationshipAssembler,
    ) {}

    public function handle(ListCustomerRelationshipsCommand $command): ListCustomerRelationshipsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        $relationships = $this->relationshipRepository->listForCustomer(
            $hqId, $command->customerId, $command->activeOnly ? $this->clock->now()->format('Y-m-d') : null);

        return new ListCustomerRelationshipsResult($this->customerRelationshipAssembler->assemble($hqId, $relationships));
    }
}
