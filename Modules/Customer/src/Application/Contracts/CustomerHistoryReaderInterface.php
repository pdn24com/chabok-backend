<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerHistoryEntryDto;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;

interface CustomerHistoryReaderInterface
{
    /**
     * Every row of one category, newest first. The rows of a category come from more than one table, so
     * they are normalised and ordered here rather than by the database.
     *
     * @return list<CustomerHistoryEntryDto>
     */
    public function entries(string $hqId, string $customerId, CustomerHistoryCategory $category): array;
}
