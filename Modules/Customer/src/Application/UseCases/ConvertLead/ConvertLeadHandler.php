<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ConvertLead;

use Illuminate\Database\ConnectionInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ConvertLeadHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function handle(ConvertLeadCommand $command): ConvertLeadResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $conversion = $command->conversion;
        // Merging into an existing customer (lifecycle MERGED) is an open decision; nothing is written for it.
        if ($conversion->mergeIntoCustomerId !== null || $conversion->confirmMerge) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                [($conversion->mergeIntoCustomerId !== null ? 'merge_into_customer_id' : 'confirm_merge') => ['customer.merging_into_an_existing_customer_is_not_available_yet']]);
        }

        $customer = $this->connection->transaction(function () use ($hqId, $command, $conversion): CustomerRecord {
            $current = $this->customerRepository->lockForTenant($hqId, $command->customerId);
            if ($current === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            if ($current->phase !== CustomerPhase::LEAD) {
                throw new ApiException(ApiErrorCode::CustomerAlreadyConverted, 409, 'customer.record_is_already_a_customer');
            }
            if ($current->lifecycle !== CustomerLifecycle::ACTIVE->value) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                    ['customer_id' => ['customer.only_an_active_lead_can_be_converted']]);
            }

            $firstName = $conversion->firstName ?? $current->first_name;
            $familyName = $conversion->familyName ?? $current->family_name;
            // A display name that was not sent follows the name fields only when they actually changed.
            $namesChanged = $firstName !== $current->first_name || $familyName !== $current->family_name;
            $displayName = $conversion->displayName
                ?? ($namesChanged && $firstName !== null && $familyName !== null ? $firstName.' '.$familyName : $current->display_name);
            $this->assertNamesAreComplete($current->kind, $firstName, $familyName, $displayName);

            $customerCode = $conversion->customerCode ?? $current->customer_code;
            if ($conversion->customerCode !== null && $conversion->customerCode !== $current->customer_code
                && $this->customerRepository->customerCodeTaken($hqId, $conversion->customerCode, $command->customerId)) {
                throw new ApiException(ApiErrorCode::CustomerCodeExists, 409, 'customer.customer_code_already_exists',
                    ['customer_code' => ['customer.customer_code_already_exists']]);
            }

            $at = $this->clock->now();
            $this->customerRepository->update($hqId, $command->customerId, [
                'first_name' => $firstName,
                'family_name' => $familyName,
                'display_name' => $displayName,
                'customer_code' => $customerCode,
                'phase' => CustomerPhase::CUSTOMER->value,
                // The date of the first promotion is kept, exactly as the profile writer keeps it.
                'converted_at' => $current->converted_at ?? $at,
                'updated_at' => $at,
            ]);

            return $this->customerRepository->lockForTenant($hqId, $command->customerId) ?? $current;
        }, attempts: 3);

        return new ConvertLeadResult($customer);
    }

    /** A customer is a named record: a person needs both name parts, and every kind needs a display name. */
    private function assertNamesAreComplete(CustomerKind $kind, ?string $firstName, ?string $familyName, ?string $displayName): void
    {
        $missing = [];
        if ($kind === CustomerKind::PERSON) {
            $firstName === null || trim($firstName) === '' ? $missing['first_name'] = ['customer.a_customer_needs_a_name'] : null;
            $familyName === null || trim($familyName) === '' ? $missing['family_name'] = ['customer.a_customer_needs_a_name'] : null;
        }
        if ($displayName === null || trim($displayName) === '') {
            $missing['display_name'] = ['customer.a_customer_needs_a_name'];
        }
        if ($missing !== []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', $missing);
        }
    }
}
