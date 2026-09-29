<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customer\Application\Dto\CustomerListFiltersDto;
use Modules\Customer\Application\Repositories\CustomerRepositoryInterface;
use Modules\Customer\Domain\Enums\CustomerKind;
use Modules\Customer\Domain\Enums\CustomerPhase;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

final class EloquentCustomerRepository implements CustomerRepositoryInterface
{
    public function create(array $attributes): CustomerRecord
    {
        return CustomerRecord::query()->forceCreate($attributes);
    }

    public function findDetailForTenant(string $hqId, string $customerId): ?CustomerRecord
    {
        return $this->ofTenant($hqId, $customerId)
            ->with([
                'assignee' => fn ($assignee) => $assignee->select(['id', 'display_name']),
                'defaultMobile',
                'defaultAddress.city' => fn ($city) => $city->select(['id', 'name_fa']),
                'primaryIndustry.industry' => fn ($industry) => $industry->select(['id', 'title']),
            ])
            ->first();
    }

    public function findProfileForTenant(string $hqId, string $customerId): ?CustomerRecord
    {
        return $this->ofTenant($hqId, $customerId)
            ->with([
                'assignee' => fn ($assignee) => $assignee->select(['id', 'display_name']),
                'primaryIndustry.industry' => fn ($industry) => $industry->select(['id', 'code', 'title']),
            ])
            ->first();
    }

    public function lockForTenant(string $hqId, string $customerId): ?CustomerRecord
    {
        return $this->ofTenant($hqId, $customerId)->lockForUpdate()->first();
    }

    public function update(string $hqId, string $customerId, array $attributes): void
    {
        $this->ofTenant($hqId, $customerId)->update($attributes);
    }

    public function existsForTenant(string $hqId, string $customerId): bool
    {
        return $this->ofTenant($hqId, $customerId)->exists();
    }

    public function customerCodeTaken(string $hqId, string $customerCode, ?string $exceptCustomerId = null): bool
    {
        $query = CustomerRecord::query()->where(['hq_id' => $hqId, 'customer_code' => $customerCode]);
        if ($exceptCustomerId !== null) {
            $query->where('customer_id', '!=', $exceptCustomerId);
        }

        return $query->exists();
    }

    public function isCompany(string $hqId, string $customerId): bool
    {
        return $this->ofTenant($hqId, $customerId)->where('kind', CustomerKind::COMPANY->value)->exists();
    }

    public function isInCustomerPhase(string $hqId, string $customerId): bool
    {
        return $this->ofTenant($hqId, $customerId)->where('phase', CustomerPhase::CUSTOMER->value)->exists();
    }

    public function displayNamesFor(string $hqId, array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        return CustomerRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('customer_id', $customerIds)
            ->pluck('display_name', 'id')
            ->mapWithKeys(fn (?string $name, int|string $id): array => [(string) $id => $name])
            ->all();
    }

    public function findSummariesForTenant(string $hqId, array $customerIds): Collection
    {
        if ($customerIds === []) {
            return new Collection;
        }

        return CustomerRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('customer_id', $customerIds)
            ->orderByDesc('id')
            ->get(['id', 'display_name', 'phase', 'kind']);
    }

    public function paginateForTenant(string $hqId, CustomerListFiltersDto $filters): LengthAwarePaginator
    {
        // The assignee name belongs to every row, so it is read once instead of per row.
        $query = CustomerRecord::query()
            ->where('hq_id', $hqId)
            ->with(['assignee' => fn ($assignee) => $assignee->select(['id', 'display_name'])]);

        foreach (['display_name' => $filters->displayName, 'customer_code' => $filters->customerCode] as $column => $value) {
            if ($value !== null) {
                $query->whereLike($column, '%'.$value.'%');
            }
        }

        foreach (['phase' => $filters->phase, 'kind' => $filters->kind, 'lifecycle' => $filters->lifecycle] as $column => $value) {
            if ($value !== null) {
                $query->where($column, $value->value);
            }
        }

        if ($filters->assigneeId !== null) {
            $query->where('assignee_id', $filters->assigneeId);
        }
        if ($filters->updatedAt !== null) {
            $query->where('updated_at', '>=', $filters->updatedAt->format('Y-m-d H:i:s'))
                ->where('updated_at', '<', $filters->updatedAt->modify('+1 day')->format('Y-m-d H:i:s'));
        }
        if ($filters->updatedAtFrom !== null) {
            $query->where('updated_at', '>=', $filters->updatedAtFrom->format('Y-m-d H:i:s'));
        }
        if ($filters->updatedAtTo !== null) {
            $query->where('updated_at', '<', $filters->updatedAtTo->modify('+1 day')->format('Y-m-d H:i:s'));
        }

        return $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate($filters->perPage, ['id', 'display_name', 'phase', 'kind', 'customer_code', 'lifecycle', 'assignee_id', 'updated_at'], page: $filters->page);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<CustomerRecord> */
    private function ofTenant(string $hqId, string $customerId)
    {
        return CustomerRecord::query()->where(['hq_id' => $hqId, 'customer_id' => $customerId]);
    }
}
