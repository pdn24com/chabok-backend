<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCompanyRelationship;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipAssemblerInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipValidatorInterface;
use Modules\Customer\Application\Dto\CustomerRelationshipDto;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CreateCompanyRelationshipHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ContactPointRepositoryInterface $contactPointRepository,
        private RelationshipRepositoryInterface $relationshipRepository,
        private CustomerRelationshipValidatorInterface $customerRelationshipValidator,
        private CustomerRelationshipAssemblerInterface $customerRelationshipAssembler,
    ) {}

    public function handle(CreateCompanyRelationshipCommand $command): CreateCompanyRelationshipResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $draft = $command->relationship;

        $relationship = $this->connection->transaction(function () use ($hqId, $command, $draft): CustomerRelationshipDto {
            // The company's row is locked first: it serialises every change of its relationships, so two
            // requests can never both take the primary slot or both open the same person twice.
            $company = $this->customerRepository->lockForTenant($hqId, $command->customerId);
            if ($company === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($company->kind !== CustomerKind::COMPANY) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['customer_id' => ['customer.relationships_are_created_from_a_company']]);
            }

            $at = $this->clock->now();
            $today = $at->format('Y-m-d');
            $mobile = $this->customerRelationshipValidator->validate($hqId, $command->customerId, $draft, $today);

            if ($draft->isPrimary) {
                $holder = $this->relationshipRepository->findOpenPrimaryForCompany($hqId, $command->customerId, $today, lock: true);
                if ($holder !== null) {
                    if (! $draft->replacePrimary) {
                        throw new ApiException(ApiErrorCode::PrimaryRelationshipExists, 409, 'customer.company_already_has_a_primary_relationship',
                            ['is_primary' => ['customer.company_already_has_a_primary_relationship']]);
                    }
                    // The slot is freed before the new holder takes it, so at no moment are there two.
                    $this->relationshipRepository->update($hqId, $holder->relationship_id, ['is_primary' => false]);
                }
            }

            $personId = $draft->personCustomerId;
            if ($draft->newPerson !== null && $mobile !== null) {
                // The person starts in the phase of the company that introduces them and answers to the same owner.
                $phase = $company->phase;
                $person = $this->customerRepository->create([
                    'hq_id' => $hqId,
                    'first_name' => $draft->newPerson->firstName,
                    'family_name' => $draft->newPerson->familyName,
                    'display_name' => $draft->newPerson->firstName.' '.$draft->newPerson->familyName,
                    'customer_code' => null,
                    'kind' => CustomerKind::PERSON,
                    'phase' => $phase,
                    'lifecycle' => 'ACTIVE',
                    'assignee_id' => $company->assignee_id,
                    'created_by' => $command->actor->userId,
                    'converted_at' => $phase === CustomerPhase::CUSTOMER ? $at : null,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);
                $personId = $person->customer_id;
            }

            $record = $this->relationshipRepository->create([
                'hq_id' => $hqId,
                'person_customer_id' => $personId,
                'company_customer_id' => $command->customerId,
                'position_id' => $draft->positionId,
                'role_title' => $draft->roleTitle,
                'decision_level' => $draft->decisionLevel,
                'signing_authority' => $draft->signingAuthority,
                'valid_from' => $draft->validFrom?->format('Y-m-d'),
                'valid_to' => $draft->validTo?->format('Y-m-d'),
                'is_primary' => $draft->isPrimary,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            if ($mobile !== null) {
                // The work number of the new person, reached through the very post that introduced them.
                $this->contactPointRepository->create([
                    'hq_id' => $hqId,
                    'customer_id' => $personId,
                    'type' => 'MOBILE',
                    'identifier_kind' => 'PHONE',
                    'value' => $mobile->value,
                    'normalized_value' => $mobile->normalized,
                    'scope' => 'WORK',
                    'is_default' => true,
                    'status' => 'ACTIVE',
                    'relationship_id' => $record->relationship_id,
                    'created_by' => $command->actor->userId,
                    'created_at' => $at,
                ]);
            }

            return $this->customerRelationshipAssembler->assemble($hqId, [$record])[0];
        }, attempts: 3);

        return new CreateCompanyRelationshipResult($relationship);
    }
}
