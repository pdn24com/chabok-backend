<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use Modules\Customer\Domain\Enums\CustomerHistoryCategory;

/** One card of the history grid: how many rows the drawer holds and the first few of them. */
final readonly class CustomerHistoryCategoryDto
{
    /** @param list<CustomerHistoryEntryDto> $preview */
    public function __construct(
        public CustomerHistoryCategory $category,
        public int $total,
        public array $preview,
    ) {}
}
