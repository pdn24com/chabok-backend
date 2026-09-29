<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

/** The crm_relationships row: a person holding a role at a company. */
interface RelationshipRepositoryInterface
{
    /** True when the relationship exists in the tenant and its person side is the given customer. */
    public function existsForPerson(string $hqId, string $personCustomerId, string $relationshipId): bool;
}
