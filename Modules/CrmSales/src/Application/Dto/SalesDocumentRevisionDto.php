<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Dto;

use DateTimeImmutable;

/**
 * The content of the next revision. Every field is optional and an omitted one is copied from the
 * revision being superseded, so re-stating a frozen document costs an empty body. Terms carry their own
 * flag, because clearing them is told apart from leaving them alone.
 */
final readonly class SalesDocumentRevisionDto
{
    public function __construct(
        public ?string $currency = null,
        public ?int $total = null,
        public ?DateTimeImmutable $expiresAt = null,
        public ?string $terms = null,
        public bool $termsSpecified = false,
    ) {}
}
