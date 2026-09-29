<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\AllocateReceiptToInvoice;

use Modules\CrmFinance\Application\Dto\FinancialAllocationDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AllocateReceiptToInvoiceCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $entryId,
        public FinancialAllocationDraftDto $input,
    ) {}
}
