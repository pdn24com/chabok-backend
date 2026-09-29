<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;
use Modules\Customer\Domain\Enums\CustomerHistoryCategory;

/**
 * One row of the customer history, normalised so entries coming from different tables sort and render
 * together. The source table stays visible through entryType, because two sources can share an id.
 */
final readonly class CustomerHistoryEntryDto
{
    public function __construct(
        public CustomerHistoryCategory $category,
        /** The table the row came from, e.g. TASK, ACTIVITY, SALES_DOCUMENT, EXTERNAL_INVOICE, AUDIT. */
        public string $entryType,
        public string $entryId,
        public string $title,
        public ?DateTimeImmutable $occurredAt = null,
        /** The kind of the row inside its source, e.g. the activity type or the document type. */
        public ?string $kind = null,
        public ?string $status = null,
        public ?string $actor = null,
        public ?int $amount = null,
    ) {}

    public function matches(?string $search): bool
    {
        return $search === null || $search === '' || mb_stripos($this->title, $search) !== false;
    }
}
