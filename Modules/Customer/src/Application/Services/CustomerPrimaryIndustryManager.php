<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Services;

use DateTimeImmutable;
use Modules\Customer\Application\Contracts\CustomerPrimaryIndustryManagerInterface;
use Modules\Customer\Application\Repositories\CustomerIndustryRepositoryInterface;

final readonly class CustomerPrimaryIndustryManager implements CustomerPrimaryIndustryManagerInterface
{
    public function __construct(private CustomerIndustryRepositoryInterface $customerIndustryRepository) {}

    /**
     * At most one primary industry per customer is a unique index over a generated column, so the old flag
     * is always cleared before the new one is set. An industry that is not linked yet is linked here.
     */
    public function move(string $hqId, string $customerId, ?string $industryId, string $actorId, DateTimeImmutable $at): void
    {
        $this->customerIndustryRepository->clearPrimary($hqId, $customerId);
        if ($industryId === null) {
            return;
        }
        if ($this->customerIndustryRepository->linkExists($hqId, $customerId, $industryId)) {
            $this->customerIndustryRepository->markPrimary($hqId, $customerId, $industryId);

            return;
        }
        $this->customerIndustryRepository->create([
            'hq_id' => $hqId,
            'customer_id' => $customerId,
            'industry_id' => $industryId,
            'is_primary' => true,
            'created_by' => $actorId,
            'created_at' => $at,
        ]);
    }
}
