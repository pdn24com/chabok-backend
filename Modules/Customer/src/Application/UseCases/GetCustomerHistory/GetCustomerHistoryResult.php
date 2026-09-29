<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\GetCustomerHistory;

use DateTimeImmutable;
use Modules\Customer\Application\Dto\CustomerHistoryCategoryDto;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/**
 * The history page of one customer. The two figures are plain observations: how long since the last
 * recorded interaction and how much work is still open. Neither carries an approved threshold, so the
 * page reports them and raises no alert of its own.
 */
final readonly class GetCustomerHistoryResult
{
    /** @param list<CustomerHistoryCategoryDto> $categories */
    public function __construct(
        public CustomerRecord $customer,
        public array $categories,
        public ?DateTimeImmutable $lastInteractionAt,
        public int $openWorkCount,
    ) {}

    public function daysSinceLastInteraction(DateTimeImmutable $now): ?int
    {
        return $this->lastInteractionAt?->diff($now)->days;
    }
}
