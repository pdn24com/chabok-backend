<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Modules\Customer\Application\Repositories\CustomerPositionRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;

final class EloquentCustomerPositionRepository implements CustomerPositionRepositoryInterface
{
    public function findForCompany(string $hqId, string $customerId, string $positionId): ?CustomerPositionRecord
    {
        return $this->ofCompany($hqId, $customerId, $positionId)->first();
    }

    public function lockForCompany(string $hqId, string $customerId, string $positionId): ?CustomerPositionRecord
    {
        return $this->ofCompany($hqId, $customerId, $positionId)->lockForUpdate()->first();
    }

    public function create(array $attributes): CustomerPositionRecord
    {
        return CustomerPositionRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $positionId, array $attributes): void
    {
        CustomerPositionRecord::query()->where(['hq_id' => $hqId, 'position_id' => $positionId])->update($attributes);
    }

    public function titlesFor(string $hqId, array $positionIds): array
    {
        if ($positionIds === []) {
            return [];
        }

        return CustomerPositionRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('position_id', $positionIds)
            ->pluck('title', 'id')
            ->mapWithKeys(fn (string $title, int|string $id): array => [(string) $id => $title])
            ->all();
    }

    /** A post belongs to a company through its department, so the owner is checked on that node. */
    private function ofCompany(string $hqId, string $customerId, string $positionId): Builder
    {
        return CustomerPositionRecord::query()
            ->where(['hq_id' => $hqId, 'position_id' => $positionId])
            ->whereHas('department', fn ($department) => $department->where('company_customer_id', $customerId));
    }
}
