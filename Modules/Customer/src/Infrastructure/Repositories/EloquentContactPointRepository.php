<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Application\Repositories\ContactPointRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

final class EloquentContactPointRepository implements ContactPointRepositoryInterface
{
    public function create(array $attributes): ContactPointRecord
    {
        return ContactPointRecord::query()->forceCreate($attributes);
    }

    public function listForCustomer(string $hqId, string $customerId): Collection
    {
        return $this->ordered($this->ofCustomer($hqId, $customerId))->get();
    }

    public function lockForCustomer(string $hqId, string $customerId): Collection
    {
        return $this->ordered($this->ofCustomer($hqId, $customerId))->lockForUpdate()->get();
    }

    public function update(string $hqId, string $contactPointId, array $attributes): void
    {
        ContactPointRecord::query()->where(['hq_id' => $hqId, 'contact_point_id' => $contactPointId])->update($attributes);
    }

    public function deleteForCustomer(string $hqId, string $customerId, array $contactPointIds): void
    {
        if ($contactPointIds === []) {
            return;
        }
        $this->ofCustomer($hqId, $customerId)->whereIn('contact_point_id', $contactPointIds)->delete();
    }

    public function findActiveOwnersOfNormalizedValue(
        string $hqId,
        string $type,
        string $normalizedValue,
        ?string $exceptCustomerId = null,
        bool $lock = false,
        int $limit = 100,
    ): Collection {
        $query = ContactPointRecord::query()
            ->where(['hq_id' => $hqId, 'type' => $type, 'normalized_value' => $normalizedValue, 'status' => 'ACTIVE']);
        if ($exceptCustomerId !== null) {
            $query->where('customer_id', '!=', $exceptCustomerId);
        }
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->orderByDesc('id')->limit($limit)->get();
    }

    /** @return Builder<ContactPointRecord> */
    private function ofCustomer(string $hqId, string $customerId): Builder
    {
        return ContactPointRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }

    /**
     * @param  Builder<ContactPointRecord>  $query
     * @return Builder<ContactPointRecord>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->orderByDesc('is_default')->orderByRaw('priority is null')->orderBy('priority')->orderBy('id');
    }
}
