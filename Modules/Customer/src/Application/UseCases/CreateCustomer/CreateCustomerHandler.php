<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomer;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerAddressValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerDraftValidatorInterface;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerAddressRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerIndustryRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;

final readonly class CreateCustomerHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerAddressRepositoryInterface $customerAddressRepository,
        private ContactPointRepositoryInterface $contactPointRepository,
        private CustomerIndustryRepositoryInterface $customerIndustryRepository,
        private CustomerAddressValidatorInterface $customerAddressValidator,
        private CustomerDraftValidatorInterface $customerDraftValidator,
    ) {}

    public function handle(CreateCustomerCommand $command): CreateCustomerResult
    {
        $hqId = $this->accessGuard->assertCanCreate($command->actor);
        $input = $command->input;

        $customer = $this->connection->transaction(function () use ($command, $hqId, $input): CustomerRecord {
            $this->customerAddressValidator->validate($input->address);
            $mobile = $this->customerDraftValidator->validate($hqId, $input);

            $at = $this->clock->now();
            $customer = $this->customerRepository->create([
                'hq_id' => $hqId,
                'first_name' => $input->firstName,
                'family_name' => $input->familyName,
                'display_name' => $input->displayName,
                'customer_code' => $input->customerCode,
                'kind' => $input->kind,
                'phase' => $input->phase,
                'lifecycle' => 'ACTIVE',
                'assignee_id' => $input->assigneeId,
                'created_by' => $command->actor->userId,
                'converted_at' => $input->phase === CustomerPhase::CUSTOMER ? $at : null,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $address = $this->customerAddressRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $customer->customer_id,
                'country_code' => $input->address->countryCode,
                'province_id' => $input->address->provinceId,
                'city_id' => $input->address->cityId,
                'foreign_city' => $input->address->foreignCity,
                'postal_code' => $input->address->postalCode,
                'address_text' => $input->address->addressText,
                'purpose' => 'MAIN',
                'is_default' => true,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $contactPoint = $this->contactPointRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $customer->customer_id,
                'type' => 'MOBILE',
                'value' => $mobile->value,
                'normalized_value' => $mobile->normalized,
                // A legal person reaches its channel through work; a natural person through a private one.
                'scope' => $input->kind === CustomerKind::COMPANY ? 'WORK' : 'PERSONAL',
                'is_default' => true,
                'status' => 'ACTIVE',
                'identifier_kind' => 'PHONE',
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);
            $industry = $input->industryId === null ? null : $this->customerIndustryRepository->create([
                'hq_id' => $hqId,
                'customer_id' => $customer->customer_id,
                'industry_id' => $input->industryId,
                'is_primary' => true,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            return $customer
                ->setRelation('defaultAddress', $address)
                ->setRelation('defaultMobile', $contactPoint)
                ->setRelation('primaryIndustry', $industry);
        }, attempts: 3);

        return new CreateCustomerResult($customer);
    }
}
