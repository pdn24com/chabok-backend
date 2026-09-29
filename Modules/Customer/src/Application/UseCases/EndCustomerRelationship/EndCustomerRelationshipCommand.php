<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\EndCustomerRelationship;

use DateTimeImmutable;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class EndCustomerRelationshipCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $relationshipId,
        public DateTimeImmutable $validTo,
    ) {}
}
