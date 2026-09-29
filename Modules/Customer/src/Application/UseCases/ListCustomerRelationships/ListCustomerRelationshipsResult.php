<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ListCustomerRelationships;

use Modules\Customer\Application\Dto\CustomerRelationshipDto;

/** The relationships of a person or a company, primary first, then newest first. */
final readonly class ListCustomerRelationshipsResult
{
    /**
     * @param  list<CustomerRelationshipDto>  $relationships
     */
    public function __construct(
        public array $relationships,
    ) {}
}
