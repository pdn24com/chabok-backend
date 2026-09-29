<?php

declare(strict_types=1);

namespace Modules\Foundation\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;

/** @mixin AccessContextDto */
final class AccessContextResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $permissionScopes = [];
        foreach ($this->permissionScopes as $permission => $scopes) {
            $permissionScopes[$permission] = array_map($this->scope(...), $scopes);
        }
        $entitlements = [];
        foreach ($this->moduleEntitlements as $entitlement) {
            $entitlements[] = ['module_code' => $entitlement->moduleCode, 'status' => $entitlement->status->value];
        }

        return [
            'hq_id' => $this->hqId,
            'tenant' => $this->tenant === null ? null : ['hq_id' => $this->tenant->hqId, 'code' => $this->tenant->code, 'title' => $this->tenant->title],
            'is_platform_admin' => $this->isPlatformAdmin,
            'role_codes' => $this->roleCodes,
            'permissions' => $this->permissions,
            'permission_scopes' => (object) $permissionScopes,
            'menu_keys' => $this->menuKeys,
            'scopes' => array_map($this->scope(...), $this->scopes),
            'accessible_node_ids' => $this->accessibleNodeIds,
            'module_entitlements' => $entitlements,
            'default_node_id' => $this->defaultNodeId,
        ];
    }

    private function scope(PermissionScope $scope): array
    {
        return ['scope_type' => $scope->type->value, 'scope_id' => $scope->id, 'includes_descendants' => $scope->includesDescendants];
    }
}
