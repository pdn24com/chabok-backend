<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Validators;

use Modules\Customer\Application\Contracts\CustomerOrgStructureValidatorInterface;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CustomerOrgStructureValidator implements CustomerOrgStructureValidatorInterface
{
    public function __construct(private CustomerRepositoryInterface $customerRepository, private CustomerDepartmentRepositoryInterface $customerDepartmentRepository) {}

    public function assertCompany(string $hqId, string $customerId): void
    {
        if (! $this->customerRepository->isCompany($hqId, $customerId)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
                ['customer_id' => ['customer.department_chart_belongs_to_a_company']]);
        }
    }

    public function assertParent(string $hqId, string $customerId, ?string $parentDepartmentId, ?string $movingDepartmentId = null): void
    {
        if ($parentDepartmentId === null) {
            return;
        }
        if ($parentDepartmentId === $movingDepartmentId) {
            throw $this->rejectParent('customer.department_cannot_be_its_own_parent');
        }
        if ($this->customerDepartmentRepository->findForCompany($hqId, $customerId, $parentDepartmentId) === null) {
            throw $this->rejectParent('customer.select_parent_department_of_the_same_company');
        }
        if ($movingDepartmentId !== null
            && in_array($movingDepartmentId, $this->customerDepartmentRepository->ancestorIds($hqId, $parentDepartmentId), true)) {
            throw $this->rejectParent('customer.department_cannot_move_under_its_own_branch');
        }
    }

    private function rejectParent(string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid',
            ['parent_department_id' => [$messageKey]]);
    }
}
