<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerHistory;

use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerHistoryReaderInterface;
use Modules\Customer\Application\Dto\CustomerHistoryCategoryDto;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/** The six cards of the history page, each with its count and the first few rows behind it. */
final readonly class GetCustomerHistoryHandler
{
    /** How many rows a card shows before the drawer is opened. */
    private const PREVIEW_SIZE = 5;

    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerHistoryReaderInterface $customerHistoryReader,
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function handle(GetCustomerHistoryCommand $command): GetCustomerHistoryResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        $customer = $this->customerRepository->findProfileForTenant($hqId, $command->customerId);
        // A customer of another tenant is indistinguishable from one that does not exist.
        if ($customer === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        $categories = [];
        $lastInteractionAt = null;
        foreach (CustomerHistoryCategory::cases() as $category) {
            $entries = $this->customerHistoryReader->entries($hqId, $command->customerId, $category);
            $categories[] = new CustomerHistoryCategoryDto($category, count($entries), array_slice($entries, 0, self::PREVIEW_SIZE));
            // The rows arrive newest first and an undated one sorts last, so the head of an interaction
            // card is the latest contact the file records.
            $newest = $entries[0]->occurredAt ?? null;
            if (in_array($category, [CustomerHistoryCategory::WORK, CustomerHistoryCategory::CORRESPONDENCE], true)
                && $newest !== null && ($lastInteractionAt === null || $newest > $lastInteractionAt)) {
                $lastInteractionAt = $newest;
            }
        }

        return new GetCustomerHistoryResult(
            customer: $customer,
            categories: $categories,
            lastInteractionAt: $lastInteractionAt,
            openWorkCount: $this->taskRepository->openForCustomer($hqId, $command->customerId)->count(),
        );
    }
}
