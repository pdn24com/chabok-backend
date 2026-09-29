<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Dto;

use DateTimeImmutable;

/** A contract as the form sends it: the file the customer signed, before it is stored. */
final readonly class ContractDraftDto
{
    public function __construct(
        public string $referenceNo,
        /** The status list is not final upstream, so this is a free UPPER_SNAKE label and never an enum. */
        public string $status = 'DRAFT',
        public ?string $opportunityId = null,
        public ?string $proformaVersionId = null,
        public ?DateTimeImmutable $startDate = null,
        public ?DateTimeImmutable $endDate = null,
        public ?int $amount = null,
        public ?string $commitments = null,
    ) {}
}
