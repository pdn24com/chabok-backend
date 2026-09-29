<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Dto;

use DateTimeImmutable;

/** A reference to an invoice issued outside the CRM, as the form sends it. */
final readonly class ExternalInvoiceDraftDto
{
    public function __construct(
        public string $externalSystem,
        public string $referenceNo,
        public int $amount,
        public DateTimeImmutable $issuedOn,
        public ?DateTimeImmutable $dueOn = null,
        public ?string $opportunityId = null,
    ) {}
}
