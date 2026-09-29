<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTask\Infrastructure\Persistence\Models\ActivityRecord;

interface ActivityRepositoryInterface
{
    /**
     * True when the tenant recorded this interaction against the named customer. An interaction offered
     * as evidence for something that happened to a customer has to be an interaction with that customer.
     */
    public function existsForCustomer(string $hqId, string $customerId, string $activityId): bool;

    /**
     * The interactions recorded against one customer, newest first, narrowed to the given types.
     *
     * @param  list<string>  $types
     * @return Collection<int, ActivityRecord>
     */
    public function historyForCustomer(string $hqId, string $customerId, array $types): Collection;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ActivityRecord;
}
