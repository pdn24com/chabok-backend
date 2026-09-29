<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerRelationshipDto;
use Modules\Customer\Infrastructure\Persistence\Models\RelationshipRecord;

interface CustomerRelationshipAssemblerInterface
{
    /**
     * Names the two ends and the post of every relationship, reading the display names and the post
     * titles of the whole list in one query each, and keeps the order of the given rows.
     *
     * @param  iterable<RelationshipRecord>  $relationships
     * @return list<CustomerRelationshipDto>
     */
    public function assemble(string $hqId, iterable $relationships): array;
}
