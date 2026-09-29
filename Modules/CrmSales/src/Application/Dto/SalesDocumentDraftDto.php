<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Dto;

use DateTimeImmutable;
use Modules\CrmSales\Domain\Enums\SalesDocumentType;

/** A new sales document together with the content of the first revision it opens on. */
final readonly class SalesDocumentDraftDto
{
    public function __construct(
        public string $opportunityId,
        public SalesDocumentType $documentType,
        public string $currency,
        public int $total,
        public DateTimeImmutable $expiresAt,
        /** Null asks the server for the next number of this type and year. */
        public ?string $documentNo = null,
        public ?string $terms = null,
    ) {}
}
