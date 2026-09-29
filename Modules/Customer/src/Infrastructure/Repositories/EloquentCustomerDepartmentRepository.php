<?php

declare(strict_types=1);

namespace Modules\Customer\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Application\Repositories\CustomerDepartmentRepositoryInterface;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

final class EloquentCustomerDepartmentRepository implements CustomerDepartmentRepositoryInterface
{
    public function listForCompany(string $hqId, string $customerId): Collection
    {
        // One query for the nodes and one for their posts; the tree is nested in the presentation.
        return $this->ofCompany($hqId, $customerId)
            ->with(['positions' => fn ($positions) => $positions->orderBy('title')])
            ->orderBy('title')
            ->get();
    }

    public function findForCompany(string $hqId, string $customerId, string $departmentId): ?CustomerDepartmentRecord
    {
        return $this->ofCompany($hqId, $customerId)
            ->where('customer_department_id', $departmentId)
            ->with(['positions' => fn ($positions) => $positions->orderBy('title')])
            ->first();
    }

    public function lockForCompany(string $hqId, string $customerId, string $departmentId): ?CustomerDepartmentRecord
    {
        return $this->ofCompany($hqId, $customerId)
            ->where('customer_department_id', $departmentId)
            ->lockForUpdate()
            ->first();
    }

    public function create(array $attributes): CustomerDepartmentRecord
    {
        return CustomerDepartmentRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $departmentId, array $attributes): void
    {
        CustomerDepartmentRecord::query()
            ->where(['hq_id' => $hqId, 'customer_department_id' => $departmentId])
            ->update($attributes);
    }

    public function ancestorIds(string $hqId, string $departmentId): array
    {
        $ancestors = [];
        $current = $departmentId;
        // The chart is a tree, so the walk ends at a root. The seen-guard only protects the walk itself
        // against a cycle that a direct write left behind.
        while ($current !== null && ! in_array($current, $ancestors, true)) {
            $parent = CustomerDepartmentRecord::query()
                ->where(['hq_id' => $hqId, 'customer_department_id' => $current])
                ->value('parent_department_id');
            $current = $parent === null ? null : (string) $parent;
            if ($current !== null) {
                $ancestors[] = $current;
            }
        }

        return $ancestors;
    }

    /** @return Builder<CustomerDepartmentRecord> */
    private function ofCompany(string $hqId, string $customerId): Builder
    {
        return CustomerDepartmentRecord::query()->where(['hq_id' => $hqId, 'company_customer_id' => $customerId]);
    }
}
