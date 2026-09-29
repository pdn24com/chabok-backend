<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Serialization;

use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

final class RoleDocument
{
    public static function serialize(RoleRecord $role): array
    {
        return [
            'role_id' => (string) $role->role_id,
            'hq_id' => $role->hq_id === null ? null : (string) $role->hq_id,
            'role_code' => (string) $role->role_code,
            'role_title' => (string) $role->role_title,
            'description' => $role->description === null ? null : (string) $role->description,
            'role_kind' => (string) $role->role_kind,
            'is_cloneable' => (bool) $role->is_cloneable,
            'status' => (string) $role->status,
            'permission_codes' => $role->permissions->sortBy('permission_code', SORT_STRING)->pluck('permission_code')->values()->all(),
            'menu_keys' => $role->menuPreference === null ? null : $role->menuItems->pluck('menu_key')->all(),
        ];
    }
}
