<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\Customer\Application\Contracts\CustomerContactPointValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerRelationshipValidatorInterface;
use Modules\Customer\Application\Dto\CompanyRelationshipDraftDto;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\ValueObjects\MobileNumber;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CustomerRelationshipValidator implements CustomerRelationshipValidatorInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private CustomerPositionRepositoryInterface $customerPositionRepository,
        private RelationshipRepositoryInterface $relationshipRepository,
        private CustomerContactPointValidatorInterface $customerContactPointValidator,
    ) {}

    public function validate(string $hqId, string $companyCustomerId, CompanyRelationshipDraftDto $draft, string $today): ?MobileNumber
    {
        if ($draft->validFrom !== null && $draft->validTo !== null && $draft->validTo < $draft->validFrom) {
            throw $this->invalid('valid_to', 'customer.valid_to_is_before_valid_from');
        }
        // An ended relationship cannot hold the primary slot; ending one releases it.
        if ($draft->isPrimary && $draft->validTo !== null && $draft->validTo->format('Y-m-d') < $today) {
            throw $this->invalid('is_primary', 'customer.an_ended_relationship_cannot_be_primary');
        }
        if ($draft->positionId !== null
            && $this->customerPositionRepository->findForCompany($hqId, $companyCustomerId, $draft->positionId) === null) {
            throw $this->invalid('position_id', 'customer.position_is_not_of_this_company');
        }

        if ($draft->newPerson === null) {
            $this->assertPersonCanJoin($hqId, $companyCustomerId, (string) $draft->personCustomerId, $today);

            return null;
        }

        $mobile = MobileNumber::tryFrom($draft->newPerson->mobile);
        if ($mobile === null) {
            throw $this->invalid('new_person.mobile', 'customer.mobile_number_is_invalid');
        }
        // A brand-new person cannot hold a relationship yet, so only the mobile rule of the tenant applies.
        $this->customerContactPointValidator->assertMobileIsFree($hqId, $mobile->normalized, null, 'new_person.mobile');

        return $mobile;
    }

    /** The person must be a PERSON of this tenant; a company, another tenant's record and a missing one read the same. */
    private function assertPersonCanJoin(string $hqId, string $companyCustomerId, string $personCustomerId, string $today): void
    {
        $person = $this->customerRepository->findSummariesForTenant($hqId, [$personCustomerId])->first();
        if ($person === null || $person->kind !== CustomerKind::PERSON) {
            throw $this->invalid('person_customer_id', 'customer.select_a_person_of_this_tenant');
        }
        if ($this->relationshipRepository->findOpenForPair($hqId, $personCustomerId, $companyCustomerId, $today, lock: true) !== null) {
            throw new ApiException(ApiErrorCode::RelationshipAlreadyExists, 409, 'customer.person_already_has_an_open_relationship_with_this_company',
                ['person_customer_id' => ['customer.person_already_has_an_open_relationship_with_this_company']]);
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
