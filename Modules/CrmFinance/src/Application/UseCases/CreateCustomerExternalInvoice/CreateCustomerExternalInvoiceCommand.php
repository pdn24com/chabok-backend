<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\UseCases\CreateCustomerExternalInvoice;

use Modules\CrmFinance\Application\Dto\ExternalInvoiceDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerExternalInvoiceCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public ExternalInvoiceDraftDto $input,
    ) {}
}
