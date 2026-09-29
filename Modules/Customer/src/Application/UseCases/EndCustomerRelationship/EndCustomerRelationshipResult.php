<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\EndCustomerRelationship;

use Modules\Customer\Application\Dto\CustomerRelationshipDto;

/** The relationship after its end date was set; it no longer holds the primary slot. */
final readonly class EndCustomerRelationshipResult
{
    public function __construct(
        public CustomerRelationshipDto $relationship,
    ) {}
}
