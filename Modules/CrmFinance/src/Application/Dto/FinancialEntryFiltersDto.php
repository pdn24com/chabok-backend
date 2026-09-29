<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Dto;

use Modules\CrmFinance\Domain\Enums\FinancialEntryKind;

/** What the ledger view asks for. A customer holds few entries, so there is no page to ask for. */
final readonly class FinancialEntryFiltersDto
{
    public function __construct(public ?FinancialEntryKind $kind = null) {}
}
