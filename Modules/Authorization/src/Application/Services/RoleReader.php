<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RoleReader
{
    public function __construct(
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\RoleNavigation $navigation,
    )
    {
    }

    public function rolePayload(string $roleId): array
    {
        $role = $this->repository->role($roleId);
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return [
            'role_id' => (string) $role->role_id,
            'hq_id' => $role->hq_id === null ? null : (string) $role->hq_id,
            'role_code' => (string) $role->role_code,
            'role_title' => (string) $role->role_title,
            'description' => $role->description === null ? null : (string) $role->description,
            'role_kind' => (string) $role->role_kind,
            'is_cloneable' => (bool) $role->is_cloneable,
            'status' => (string) $role->status,
            'permission_codes' => $this->permissionCodesForRole($roleId),
            'menu_keys' => $this->navigation->forRole($roleId),
        ];
    }

    public function permissionCodesForRole(string $roleId): array
    {
        return $this->repository->permissionCodesForRole($roleId);
    }
}
