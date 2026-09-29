<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Dto;

/** How much of one receipt the operator is setting against one external invoice. */
final readonly class FinancialAllocationDraftDto
{
    public function __construct(public string $invoiceId, public int $amount) {}
}
