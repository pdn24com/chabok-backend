<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerHistoryEntries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customer\Application\Contracts\CustomerAccessGuardInterface;
use Modules\Customer\Application\Contracts\CustomerHistoryReaderInterface;
use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

/** One drawer of the history page, and with no category the continuous timeline across all of them. */
final readonly class ListCustomerHistoryEntriesHandler
{
    public function __construct(
        private CustomerAccessGuardInterface $accessGuard,
        private CustomerRepositoryInterface $customerRepository,
        private CustomerHistoryReaderInterface $customerHistoryReader,
    ) {}

    public function handle(ListCustomerHistoryEntriesCommand $command): ListCustomerHistoryEntriesResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        if (! $this->customerRepository->existsForTenant($hqId, $command->customerId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        $categories = $command->category === null ? CustomerHistoryCategory::cases() : [$command->category];
        $entries = [];
        foreach ($categories as $category) {
            foreach ($this->customerHistoryReader->entries($hqId, $command->customerId, $category) as $entry) {
                if ($entry->matches($command->search)) {
                    $entries[] = $entry;
                }
            }
        }
        // The timeline merges categories that were each already ordered, so the union is ordered again.
        if ($command->category === null) {
            usort($entries, static fn (CustomerHistoryEntryDto $left, CustomerHistoryEntryDto $right): int => ($right->occurredAt?->getTimestamp() ?? 0) <=> ($left->occurredAt?->getTimestamp() ?? 0));
        }

        // The rows of one customer are counted in the hundreds and come from several tables, so they are
        // merged in memory and paged here rather than through a union query.
        return new ListCustomerHistoryEntriesResult(new LengthAwarePaginator(
            array_slice($entries, ($command->page - 1) * $command->perPage, $command->perPage),
            count($entries),
            $command->perPage,
            $command->page,
        ));
    }
}
