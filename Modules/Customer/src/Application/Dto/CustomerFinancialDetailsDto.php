<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;
use Modules\Customer\Domain\Enums\CreditRating;

/**
 * The manual financial summary of a customer. Every figure is typed in rather than computed, so the
 * reference date and the source note the figures came from are demanded alongside them. The summary is
 * never reconciled against the individual financial entries; the two are separate readings of the account.
 */
final readonly class CustomerFinancialDetailsDto
{
    public function __construct(
        public DateTimeImmutable $financialReferenceDate,
        public string $sourceNote,
        /** A warning threshold only: nothing in the system is blocked when it is passed. */
        public ?int $creditLimit = null,
        public ?CreditRating $creditRating = null,
        public ?string $settlementTerms = null,
        public ?string $accountingCode = null,
        public ?string $accountingTitle = null,
        public ?int $revenue = null,
        public ?int $receipts = null,
        public ?int $directCost = null,
        public ?int $balance = null,
    ) {}
}
