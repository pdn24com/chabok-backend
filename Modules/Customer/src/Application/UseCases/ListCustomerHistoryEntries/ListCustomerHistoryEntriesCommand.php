<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerHistoryEntries;

use Modules\Customer\Domain\Enums\CustomerHistoryCategory;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerHistoryEntriesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        /** Null reads every category at once, which is what the linear timeline shows. */
        public ?CustomerHistoryCategory $category = null,
        public ?string $search = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
