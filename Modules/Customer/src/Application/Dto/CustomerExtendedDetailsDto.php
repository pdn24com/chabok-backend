<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;

/**
 * The supplementary details of a customer as the form sends them back. The write replaces the whole set,
 * so a field left out of the request is cleared and needs no separate *Specified flag.
 */
final readonly class CustomerExtendedDetailsDto
{
    public function __construct(
        public ?string $salutation = null,
        public ?DateTimeImmutable $birthDate = null,
        public ?string $tradeName = null,
        public ?string $legalForm = null,
        public ?string $legalName = null,
        /** The registration number of the company, which is not its national ID. */
        public ?string $registrationNo = null,
        public ?DateTimeImmutable $registrationDate = null,
        public ?string $registrationPlace = null,
        public ?string $needSummary = null,
        public ?int $budget = null,
        public ?bool $budgetKnown = null,
        public ?string $authorityNote = null,
        public ?bool $needConfirmed = null,
        public ?string $timeframe = null,
        public ?string $qualificationResult = null,
    ) {}
}
