<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Application\UseCases\GetCustomerOrgStructure\GetCustomerOrgStructureResult;
use Modules\Customer\Infrastructure\Persistence\Models\CustomerDepartmentRecord;

/**
 * The department chart of one company as a tree. The rows arrive flat and are nested here in one pass,
 * so the depth is whatever the data holds and no query runs per level.
 *
 * @mixin GetCustomerOrgStructureResult
 */
final class CustomerOrgStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $company = $this->company;

        return [
            'company' => [
                'customer_id' => $company->customer_id,
                'kind' => $company->kind->value,
                'display_name' => $company->display_name,
                'customer_code' => $company->customer_code,
            ],
            'departments' => $this->tree($request),
        ];
    }

    private function tree(Request $request): array
    {
        $childrenByParent = [];
        foreach ($this->departments as $department) {
            $parent = $department->parent_department_id === null ? '' : (string) $department->parent_department_id;
            $childrenByParent[$parent][] = $department;
        }

        return $this->branch($request, $childrenByParent, '');
    }

    /** @param array<string, list<CustomerDepartmentRecord>> $childrenByParent */
    private function branch(Request $request, array $childrenByParent, string $parentKey): array
    {
        $nodes = [];
        foreach ($childrenByParent[$parentKey] ?? [] as $department) {
            $nodes[] = [
                ...(new CustomerDepartmentResource($department))->resolve($request),
                'children' => $this->branch($request, $childrenByParent, $department->customer_department_id),
            ];
        }

        return $nodes;
    }
}
