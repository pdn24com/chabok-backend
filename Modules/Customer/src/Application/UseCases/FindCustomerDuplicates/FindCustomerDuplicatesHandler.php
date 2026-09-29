<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\FindCustomerDuplicates;

use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Dto\CustomerDuplicateDto;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\ContactPointType;
use Modules\Customer\Domain\ValueObjects\MobileNumber;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class FindCustomerDuplicatesHandler
{
    /** A lookup that names more records than this is already a reason to stop and look, so the rest is cut off. */
    private const LIMIT = 20;

    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private ContactPointRepositoryInterface $contactPointRepository,
    ) {}

    public function handle(FindCustomerDuplicatesCommand $command): FindCustomerDuplicatesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $mobile = $command->mobile === null ? null : MobileNumber::tryFrom($command->mobile);
        if ($command->mobile !== null && $mobile === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['mobile' => ['customer.mobile_number_is_invalid']]);
        }
        $email = $command->email === null ? null : mb_strtolower(trim($command->email));
        if ($mobile === null && $email === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['mobile' => ['customer.duplicate_lookup_needs_mobile_or_email']]);
        }

        // A record found by its mobile number keeps that match even when its email matches too: the
        // number is the identity the one-person-per-mobile rule protects, the email is only a hint.
        $matches = [];
        if ($email !== null) {
            foreach ($this->contactPointRepository->findActiveOwnersOfNormalizedValue($hqId, ContactPointType::EMAIL->value, $email) as $row) {
                $matches[$row->customer_id] = 'EMAIL';
            }
        }
        if ($mobile !== null) {
            foreach ($this->contactPointRepository->findActiveOwnersOfNormalizedValue($hqId, ContactPointType::MOBILE->value, $mobile->normalized) as $row) {
                $matches[$row->customer_id] = 'MOBILE';
            }
        }

        $duplicates = [];
        foreach ($this->customerRepository->findSummariesForTenant($hqId, array_map('strval', array_keys($matches))) as $customer) {
            $duplicates[] = new CustomerDuplicateDto($customer->customer_id, $customer->display_name, $customer->phase, $matches[$customer->customer_id]);
            if (count($duplicates) === self::LIMIT) {
                break;
            }
        }

        return new FindCustomerDuplicatesResult($duplicates);
    }
}
