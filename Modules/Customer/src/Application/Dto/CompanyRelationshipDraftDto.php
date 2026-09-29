<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;

/**
 * The relationship a company's tab submits. The person is either an existing one or a new one, never both;
 * the request layer refuses any other combination before this object is built. The dates are calendar days.
 */
final readonly class CompanyRelationshipDraftDto
{
    public function __construct(
        public ?string $personCustomerId,
        public ?NewPersonDto $newPerson,
        public ?string $positionId,
        public string $roleTitle,
        public ?string $decisionLevel,
        public ?string $signingAuthority,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public bool $isPrimary,
        public bool $replacePrimary,
    ) {}
}
