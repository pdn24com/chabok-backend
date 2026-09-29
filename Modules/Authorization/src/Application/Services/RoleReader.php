<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Repositories\PermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class RoleReader implements RoleReaderInterface
{
    public function __construct(
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
    ) {}

    public function role(string $roleId): RoleRecord
    {
        $role = $this->roleRepository->findWithGrants($roleId);
        if ($role === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $role;
    }

    public function visibleRoles(?string $hqId): Collection
    {
        return $this->roleRepository->visibleWithGrants($hqId);
    }

    public function permissionCodesForRole(string $roleId): array
    {
        return $this->permissionRepository->codesForRole($roleId);
    }
}
