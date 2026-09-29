<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\EndCustomerRelationship;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipAssemblerInterface;
use Modules\Customer\Application\Dto\CustomerRelationshipDto;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class EndCustomerRelationshipHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private RelationshipRepositoryInterface $relationshipRepository,
        private CustomerRelationshipAssemblerInterface $customerRelationshipAssembler,
    ) {}

    public function handle(EndCustomerRelationshipCommand $command): EndCustomerRelationshipResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);

        $relationship = $this->connection->transaction(function () use ($hqId, $command): CustomerRelationshipDto {
            $current = $this->relationshipRepository->lockForTenant($hqId, $command->relationshipId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $validTo = $command->validTo->format('Y-m-d');
            // A relationship counts as ended once its end date lies before today; one ending today or later still runs.
            if ($current->valid_to !== null && $current->valid_to->format('Y-m-d') < $this->clock->now()->format('Y-m-d')) {
                throw new ApiException(ApiErrorCode::RelationshipAlreadyEnded, 409, 'customer.relationship_has_already_ended');
            }
            if ($current->valid_from !== null && $validTo < $current->valid_from->format('Y-m-d')) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['valid_to' => ['customer.valid_to_is_before_valid_from']]);
            }

            // Whatever the end date, the relationship gives up the primary slot of its company.
            $this->relationshipRepository->update($hqId, $command->relationshipId, ['valid_to' => $validTo, 'is_primary' => false]);

            return $this->customerRelationshipAssembler->assemble(
                $hqId, [$this->relationshipRepository->findForTenant($hqId, $command->relationshipId) ?? $current])[0];
        }, attempts: 3);

        return new EndCustomerRelationshipResult($relationship);
    }
}
