<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerIndustries;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerIndustryValidatorInterface;
use Modules\Customer\Application\Contracts\CustomerPrimaryIndustryManagerInterface;
use Modules\Customer\Application\Dto\CustomerIndustryDraftDto;
use Modules\Customer\Application\Repositories\CustomerIndustryRepositoryInterface;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerIndustryRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class SaveCustomerIndustriesHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerIndustryRepositoryInterface $customerIndustryRepository,
        private CustomerIndustryValidatorInterface $customerIndustryValidator,
        private CustomerPrimaryIndustryManagerInterface $customerPrimaryIndustryManager,
    ) {}

    public function handle(SaveCustomerIndustriesCommand $command): SaveCustomerIndustriesResult
    {
        $hqId = $this->accessGuard->assertCanEdit($command->actor);
        $this->customerIndustryValidator->validate($hqId, $command->items);

        $industries = $this->connection->transaction(function () use ($hqId, $command): Collection {
            // The customer's row is locked first, so two replacements of the same set run one after the other.
            if ($this->customerRepository->lockForTenant($hqId, $command->customerId) === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }

            $existingIds = $this->customerIndustryRepository->lockForCustomer($hqId, $command->customerId)
                ->map(static fn (CustomerIndustryRecord $row): string => $row->industry_id)->values()->all();
            $wantedIds = array_map(static fn (CustomerIndustryDraftDto $item): string => $item->industryId, $command->items);

            $this->customerIndustryRepository->deleteForCustomer($hqId, $command->customerId,
                array_values(array_diff($existingIds, $wantedIds)));

            $now = $this->clock->now();
            foreach (array_diff($wantedIds, $existingIds) as $industryId) {
                $this->customerIndustryRepository->create([
                    'hq_id' => $hqId,
                    'customer_id' => $command->customerId,
                    'industry_id' => $industryId,
                    'is_primary' => false,
                    'created_by' => $command->actor->userId,
                    'created_at' => $now,
                ]);
            }

            // The primary flag is moved by the same manager the profile's primary_industry_id uses, so both
            // entry points keep one rule: the old flag is cleared before the new one is set.
            $primary = array_values(array_filter($command->items, static fn (CustomerIndustryDraftDto $item): bool => $item->isPrimary))[0] ?? null;
            $this->customerPrimaryIndustryManager->move($hqId, $command->customerId, $primary?->industryId, $command->actor->userId, $now);

            return $this->customerIndustryRepository->listForCustomer($hqId, $command->customerId);
        }, attempts: 3);

        return new SaveCustomerIndustriesResult($industries);
    }
}
