<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\CrmCatalog\Application\Repositories\IndustryRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerDraftValidatorInterface;
use Modules\Customer\Application\Dto\CustomerDraftDto;
use Modules\Customer\Domain\ValueObjects\MobileNumber;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class CustomerDraftValidator implements CustomerDraftValidatorInterface
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private IndustryRepositoryInterface $industryRepository,
    ) {}

    public function validate(string $hqId, CustomerDraftDto $draft): MobileNumber
    {
        $mobile = MobileNumber::tryFrom($draft->mobile);
        if ($mobile === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['mobile' => ['customer.mobile_number_is_invalid']]);
        }
        $this->validateAssignee($hqId, $draft->assigneeId);
        $this->validateIndustry($hqId, $draft->industryId);

        return $mobile;
    }

    private function validateAssignee(string $hqId, ?string $assigneeId): void
    {
        if ($assigneeId === null) {
            return;
        }
        if ($this->userRepository->findByTenant($hqId, $assigneeId)?->status !== 'ACTIVE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['assignee_id' => ['customer.select_active_assignee_from_tenant_users']]);
        }
    }

    private function validateIndustry(string $hqId, ?string $industryId): void
    {
        if ($industryId === null) {
            return;
        }
        if (! $this->industryRepository->activeExistsInTenant($hqId, $industryId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['industry_id' => ['customer.select_active_industry_from_reference_data']]);
        }
    }
}
