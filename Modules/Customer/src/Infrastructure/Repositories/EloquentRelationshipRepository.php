<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    public function create(array $attributes): RelationshipRecord
    {
        return RelationshipRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $relationshipId, array $attributes): void
    {
        RelationshipRecord::query()->where(['hq_id' => $hqId, 'relationship_id' => $relationshipId])->update($attributes);
    }

    public function findForTenant(string $hqId, string $relationshipId): ?RelationshipRecord
    {
        return $this->ofTenant($hqId, $relationshipId)->first();
    }

    public function lockForTenant(string $hqId, string $relationshipId): ?RelationshipRecord
    {
        return $this->ofTenant($hqId, $relationshipId)->lockForUpdate()->first();
    }

    public function listForCustomer(string $hqId, string $customerId, ?string $today = null): Collection
    {
        $query = RelationshipRecord::query()
            ->where('hq_id', $hqId)
            ->where(fn (Builder $side) => $side->where('person_customer_id', $customerId)->orWhere('company_customer_id', $customerId));
        if ($today !== null) {
            $query->where(fn (Builder $end) => $end->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today))
                ->where(fn (Builder $start) => $start->whereNull('valid_from')->orWhereDate('valid_from', '<=', $today));
        }

        return $query->orderByDesc('is_primary')->orderByDesc('id')->get();
    }

    public function findOpenForPair(string $hqId, string $personCustomerId, string $companyCustomerId, string $today, bool $lock = false): ?RelationshipRecord
    {
        $query = $this->notEndedBy(RelationshipRecord::query()
            ->where(['hq_id' => $hqId, 'person_customer_id' => $personCustomerId, 'company_customer_id' => $companyCustomerId]), $today);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function findOpenPrimaryForCompany(string $hqId, string $companyCustomerId, string $today, bool $lock = false): ?RelationshipRecord
    {
        $query = $this->notEndedBy(RelationshipRecord::query()
            ->where(['hq_id' => $hqId, 'company_customer_id' => $companyCustomerId, 'is_primary' => true]), $today);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    /**
     * @param  Builder<RelationshipRecord>  $query
     * @return Builder<RelationshipRecord>
     */
    private function notEndedBy(Builder $query, string $today): Builder
    {
        return $query->where(fn (Builder $end) => $end->whereNull('valid_to')->orWhereDate('valid_to', '>=', $today));
    }

    /** @return Builder<RelationshipRecord> */
    private function ofTenant(string $hqId, string $relationshipId): Builder
    {
        return RelationshipRecord::query()->where(['hq_id' => $hqId, 'relationship_id' => $relationshipId]);
    }
}
