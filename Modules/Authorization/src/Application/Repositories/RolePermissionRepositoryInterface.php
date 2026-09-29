<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;

interface RolePermissionRepositoryInterface
{
    /** @param list<array<string, mixed>> $rows */
    public function insert(array $rows): void;

    public function deleteForRole(string $roleId): void;

    /** Grants of active Permissions for the given Roles, eager-loaded so a context build stays at one read. @param list<string> $roleIds @return Collection<int, \Modules\Authorization\Infrastructure\Persistence\Models\RolePermissionRecord> */
    public function activeGrantsForRoles(array $roleIds): Collection;
}
