<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerRelationships;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCustomerRelationshipsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        /** Only the relationships running today; false returns every relationship, ended ones included. */
        public bool $activeOnly,
    ) {}
}
