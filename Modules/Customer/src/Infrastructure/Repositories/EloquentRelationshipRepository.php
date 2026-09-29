<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Modules\Customer\Application\Repositories\RelationshipRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\RelationshipRecord;

final class EloquentRelationshipRepository implements RelationshipRepositoryInterface
{
    public function existsForPerson(string $hqId, string $personCustomerId, string $relationshipId): bool
    {
        return RelationshipRecord::query()
            ->where(['hq_id' => $hqId, 'person_customer_id' => $personCustomerId, 'relationship_id' => $relationshipId])
            ->exists();
    }
}
