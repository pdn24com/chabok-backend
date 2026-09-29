<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Dto;

use DateTimeImmutable;
use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;

/**
 * A new financial line of a customer. An entry is never edited afterwards, so a correction arrives here
 * as a fresh entry naming the one it reverses.
 */
final readonly class FinancialEntryDraftDto
{
    public function __construct(
        public FinancialEntryKind $kind,
        public int $amount,
        public DateTimeImmutable $effectiveOn,
        public string $sourceRef,
        public ?string $invoiceId = null,
        public ?string $reversesId = null,
    ) {}
}
