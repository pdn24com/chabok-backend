<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

use DateTimeImmutable;

/** One relationship with both ends and the post already named, ready to be shown. */
final readonly class CustomerRelationshipDto
{
    public function __construct(
        public string $relationshipId,
        public string $personCustomerId,
        public ?string $personDisplayName,
        public string $companyCustomerId,
        public ?string $companyDisplayName,
        public ?string $positionId,
        public ?string $positionTitle,
        public string $roleTitle,
        public ?string $decisionLevel,
        public ?string $signingAuthority,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validTo,
        public bool $isPrimary,
    ) {}
}
